<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CheckoutSession;
use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Design System §6.6: "dedicated page, not modal, for payment-flow
 * robustness and back-button safety." This controller is a thin web
 * presentation over App\Services\CheckoutService/CartService/
 * ShippingService — the exact same services the already-tested
 * /api/v1/checkout endpoints call (tests/Feature/Commerce/
 * CheckoutFlowTest.php). No checkout business logic is duplicated here.
 *
 * The spec's 4-step flow (address -> delivery -> payment -> review) is
 * collapsed to 3 pages: review happens inline on the payment step rather
 * than as its own page (flagged in CHANGELOG.md) — everything collected
 * so far is already visible there, so a dedicated review-only page
 * would be a confirmation click with nothing new to show.
 */
class CheckoutController extends Controller
{
    public function start(CartService $cartService, CheckoutService $checkoutService): RedirectResponse
    {
        $cart = $cartService->getOrCreateCart(Auth::user(), session()->getId());

        try {
            $session = $checkoutService->start($cart, Auth::user());
        } catch (ValidationException $e) {
            return redirect()->route('storefront.home')->withErrors($e->validator);
        }

        return redirect()->route('storefront.checkout.address', $session);
    }

    public function showAddress(CheckoutSession $checkoutSession): View
    {
        $this->authorizeSession($checkoutSession);

        return view('storefront.checkout.address', [
            'checkoutSession' => $checkoutSession,
            'addresses' => Auth::check() ? Auth::user()->addresses()->latest()->get() : collect(),
        ]);
    }

    public function storeAddress(Request $request, CheckoutSession $checkoutSession, CheckoutService $checkoutService): RedirectResponse
    {
        $this->authorizeSession($checkoutSession);

        $data = $request->validate([
            'guest_email' => [Auth::check() ? 'nullable' : 'required', 'email'],
            'guest_phone' => ['nullable', 'string'],
            'address_id' => ['nullable', 'exists:addresses,id'],
            'label' => ['required_without:address_id', 'string', 'max:100'],
            'recipient_name' => ['required_without:address_id', 'string', 'max:255'],
            'phone' => ['required_without:address_id', 'string', 'max:50'],
            'province' => ['required_without:address_id', 'string', 'max:100'],
            'city' => ['required_without:address_id', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'street_address' => ['required_without:address_id', 'string', 'max:255'],
        ]);

        if (! empty($data['address_id'])) {
            $address = Address::findOrFail($data['address_id']);
            abort_unless(Auth::check() && $address->user_id === Auth::id(), 403);
        } elseif (Auth::check()) {
            $address = Auth::user()->addresses()->create(collect($data)->only(['label', 'recipient_name', 'phone', 'province', 'city', 'area', 'street_address'])->all());
        } else {
            // TDD §5.9: guest checkout never requires an account — the
            // address is kept against the checkout session's own
            // contact details only, not persisted to a user record that
            // doesn't exist.
            $address = Address::create(collect($data)->only(['label', 'recipient_name', 'phone', 'province', 'city', 'area', 'street_address'])->all());
        }

        if (! Auth::check()) {
            $checkoutSession->update(['guest_email' => $data['guest_email'], 'guest_phone' => $data['guest_phone'] ?? null]);
        }

        $checkoutService->setAddress($checkoutSession, $address);

        return redirect()->route('storefront.checkout.delivery', $checkoutSession);
    }

    public function showDelivery(CheckoutSession $checkoutSession): View
    {
        $this->authorizeSession($checkoutSession);

        $groups = $checkoutSession->cart->itemsGroupedByStore();

        // TDD §3.4 module 26: real per-seller, per-zone, per-method rate
        // cards (Run 1.6) drive the options shown here — the buyer picks
        // a zone and method, never a self-reported fee.
        $rateCardsByStore = $groups->mapWithKeys(function ($items, $storeId) {
            $sellerId = $items->first()->variant->product->store->seller_id;

            return [$storeId => DeliveryRateCard::where('seller_id', $sellerId)->where('enabled', true)->with('zone')->get()];
        });

        return view('storefront.checkout.delivery', [
            'checkoutSession' => $checkoutSession,
            'groups' => $groups,
            'rateCardsByStore' => $rateCardsByStore,
            'zones' => DeliveryZone::whereIn('level', ['area', 'city'])->orderBy('name')->get(),
        ]);
    }

    /**
     * `selection` in the validated request data is `array<string, array{rate_card_id: string}>`, keyed by store id.
     */
    public function storeDelivery(Request $request, CheckoutSession $checkoutSession, CheckoutService $checkoutService): RedirectResponse
    {
        $this->authorizeSession($checkoutSession);

        $data = $request->validate([
            'selection' => ['required', 'array'],
            'selection.*' => ['required', 'integer', 'exists:delivery_rate_cards,id'],
        ]);

        $groups = $checkoutSession->cart->itemsGroupedByStore();

        // TDD §3.4 module 26: the buyer picks a rate card (zone + method
        // already fixed by the seller's own configuration); the fee
        // snapshotted into delivery_selection is read from that rate
        // card, never typed in by the buyer. The rate card's seller is
        // re-verified against the store it was submitted for, so a
        // tampered request can't borrow another seller's (cheaper) rate.
        $selectionByStoreId = collect($data['selection'])->mapWithKeys(function ($rateCardId, $storeId) use ($groups) {
            $sellerId = $groups->get((int) $storeId)?->first()->variant->product->store->seller_id;
            $rateCard = DeliveryRateCard::where('id', $rateCardId)->where('seller_id', $sellerId)->firstOrFail();

            return [$storeId => ['fee' => (string) $rateCard->base_fee, 'rate_card_id' => $rateCard->id]];
        })->all();

        $checkoutService->setDeliveryMethod($checkoutSession, $selectionByStoreId);

        return redirect()->route('storefront.checkout.payment', $checkoutSession);
    }

    public function showPayment(CheckoutSession $checkoutSession, PaymentGatewayManager $gateways): View
    {
        $this->authorizeSession($checkoutSession);

        return view('storefront.checkout.payment', [
            'checkoutSession' => $checkoutSession,
            'groups' => $checkoutSession->cart->itemsGroupedByStore(),
            'providers' => $gateways->availableProviders(),
        ]);
    }

    public function storePayment(Request $request, CheckoutSession $checkoutSession, CheckoutService $checkoutService): RedirectResponse|View
    {
        $this->authorizeSession($checkoutSession);

        $data = $request->validate(['provider' => ['required', 'in:pesepay,paynow']]);

        try {
            $result = $checkoutService->initiatePayment($checkoutSession, $data['provider']);
        } catch (ValidationException $e) {
            return redirect()->route('storefront.checkout.delivery', $checkoutSession)->withErrors($e->validator);
        }

        if ($result->redirectUrl !== null) {
            return redirect()->away($result->redirectUrl);
        }

        // TDD §6.6: "payment failure recovery: dedicated state (not a
        // silent redirect)." A gateway that returns instructions instead
        // of a redirect (e.g. Paynow's "Message" flow) lands here rather
        // than silently appearing to hang.
        return view('storefront.checkout.pending', [
            'checkoutSession' => $checkoutSession,
            'payment' => $result->payment,
            'instructions' => $result->instructions,
        ]);
    }

    /**
     * TDD §7.4 `return_url` / Design System §6.6: where the buyer lands
     * back on our site after paying on the gateway's own page. Gateway-
     * specific return query params are unverified against a live account
     * (same caveat as the gateways themselves), so this deliberately
     * doesn't try to parse them — it looks up whichever checkout session
     * this browser's own session already owns, which `initiatePayment()`
     * set `order_id` on before ever redirecting away.
     */
    public function return(): RedirectResponse
    {
        $session = Auth::check()
            ? CheckoutSession::where('user_id', Auth::id())->whereNotNull('order_id')->latest('id')->first()
            : CheckoutSession::whereHas('cart', fn ($q) => $q->where('session_id', session()->getId()))
                ->whereNotNull('order_id')->latest('id')->first();

        abort_if($session === null, 404);

        return match ($session->status) {
            'order_confirmed' => redirect()->route('storefront.checkout.confirmation', $session),
            'payment_failed' => redirect()->route('storefront.checkout.failed', $session),
            default => redirect()->route('storefront.checkout.payment', $session)
                ->with('status', 'Still confirming your payment — refresh in a moment, or try again below.'),
        };
    }

    public function failed(CheckoutSession $checkoutSession): View
    {
        $this->authorizeSession($checkoutSession);

        return view('storefront.checkout.failed', ['checkoutSession' => $checkoutSession]);
    }

    public function confirmation(CheckoutSession $checkoutSession): View
    {
        $this->authorizeSession($checkoutSession);

        abort_unless($checkoutSession->order_id !== null, 404);

        return view('storefront.checkout.confirmation', [
            'order' => $checkoutSession->order->load('orderGroups.items.variant.product', 'orderGroups.shipment', 'payments'),
        ]);
    }

    /**
     * Design System §6.6: "account creation is a post-purchase, one-field
     * (just set a password) upsell, never a pre-purchase gate." Only
     * reachable for an order with a guest_email (no account to upsell
     * otherwise), and only once — an existing account with that email
     * fails the uniqueness check below rather than silently taking over
     * a stranger's account.
     */
    public function upsell(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->user_id !== null || $order->guest_email === null, 404);

        if (User::where('email', $order->guest_email)->exists()) {
            return back()->withErrors(['password' => 'An account with this email already exists — sign in instead.']);
        }

        $data = $request->validate(['password' => ['required', 'string', Password::default()]]);

        $user = User::create([
            'name' => $order->guest_email,
            'email' => $order->guest_email,
            'password' => Hash::make($data['password']),
        ]);
        $user->assignRole('buyer');
        $order->update(['user_id' => $user->id]);

        Auth::login($user);

        return redirect()->route('dashboard.orders.show', $order);
    }

    private function authorizeSession(CheckoutSession $checkoutSession): void
    {
        $owns = Auth::check()
            ? $checkoutSession->user_id === Auth::id()
            : $checkoutSession->cart->session_id === session()->getId();

        abort_unless($owns, 404);
    }
}

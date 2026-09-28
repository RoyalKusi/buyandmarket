<?php

namespace App\Services;

use App\Contracts\PaymentInitiationResult;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TDD §5.9 checkout state machine: CartReview -> AddressSelection ->
 * DeliveryMethod -> PaymentProcessing -> OrderConfirmed (or ->
 * PaymentFailed, retryable without re-entering anything already
 * collected).
 */
class CheckoutService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function start(Cart $cart, ?User $user, ?string $guestEmail = null, ?string $guestPhone = null): CheckoutSession
    {
        $this->assertCartIsPurchasable($cart);

        return CheckoutSession::create([
            'cart_id' => $cart->id,
            'user_id' => $user?->id,
            'guest_email' => $guestEmail,
            'guest_phone' => $guestPhone,
            'status' => 'cart_review',
            'expires_at' => now()->addMinutes((int) config('commerce.checkout_session_ttl_minutes')),
        ]);
    }

    public function setAddress(CheckoutSession $session, Address $address): CheckoutSession
    {
        $this->assertNotExpired($session);

        $session->update(['address_id' => $address->id, 'status' => 'address_selection']);

        return $session;
    }

    /**
     * @param  array<int, array{fee: string}>  $selectionByStoreId
     */
    public function setDeliveryMethod(CheckoutSession $session, array $selectionByStoreId): CheckoutSession
    {
        $this->assertNotExpired($session);

        if ($session->address_id === null) {
            throw ValidationException::withMessages(['address' => 'Select a delivery address first.']);
        }

        $session->update(['delivery_selection' => $selectionByStoreId, 'status' => 'delivery_method']);

        return $session;
    }

    public function initiatePayment(CheckoutSession $session, string $provider): PaymentInitiationResult
    {
        $this->assertNotExpired($session);

        if ($session->delivery_selection === null) {
            throw ValidationException::withMessages(['delivery' => 'Select a delivery method first.']);
        }

        return DB::transaction(function () use ($session, $provider) {
            // A retry after PaymentFailed reuses the same order rather
            // than creating a duplicate (TDD §5.9: "retry requires only
            // re-attempting payment, not re-entering the order").
            $order = $session->order ?? $this->orderService->createFromCheckoutSession($session);

            if ($session->order_id === null) {
                $session->update(['order_id' => $order->id]);
            }

            $session->update(['status' => 'payment_processing']);

            return $this->gateways->for($provider)->initiate($order);
        });
    }

    public function markPaymentFailed(CheckoutSession $session): CheckoutSession
    {
        // TDD §5.9: "checkout_sessions retains cart contents, selected
        // address and delivery method through a PaymentFailed state for
        // the session's remaining TTL, so retry requires only
        // re-attempting payment." Nothing here is cleared.
        $session->update(['status' => 'payment_failed']);

        return $session;
    }

    public function confirmOrder(CheckoutSession $session): CheckoutSession
    {
        $session->update(['status' => 'order_confirmed']);

        return $session;
    }

    private function assertNotExpired(CheckoutSession $session): void
    {
        if ($session->isExpired()) {
            throw ValidationException::withMessages([
                'checkout' => 'This checkout session has expired. Please start again.',
            ]);
        }
    }

    /**
     * TDD §3.4 module 17: "re-validation pass at checkout in case of a
     * price/stock change."
     */
    private function assertCartIsPurchasable(Cart $cart): void
    {
        $cart->load('items.variant.product');

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        foreach ($cart->items as $item) {
            if ($item->variant->product->status !== 'published') {
                throw ValidationException::withMessages([
                    'cart' => "\"{$item->variant->product->title}\" is no longer available.",
                ]);
            }

            if ($item->variant->stock_quantity < $item->quantity) {
                throw ValidationException::withMessages([
                    'cart' => "Only {$item->variant->stock_quantity} of \"{$item->variant->product->title}\" left in stock.",
                ]);
            }
        }
    }
}

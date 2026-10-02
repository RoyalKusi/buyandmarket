<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TDD §3.6 module 27 / Design System §6.11: the buyer dashboard is
 * everyone's home base — every user is a buyer first (§3.1 module 1).
 */
class BuyerController extends Controller
{
    public function orders(Request $request): View
    {
        $orders = $request->user()->orders()
            ->with('orderGroups')
            ->latest()
            ->paginate(10);

        return view('dashboard.buyer.orders', ['orders' => $orders]);
    }

    public function showOrder(Order $order): View
    {
        $this->authorize('view', $order);

        return view('dashboard.buyer.order', [
            'order' => $order->load('orderGroups.items.variant.product', 'orderGroups.shipment.events', 'payments'),
        ]);
    }

    public function addresses(Request $request): View
    {
        $addresses = $request->user()->addresses()->latest()->get();

        return view('dashboard.buyer.addresses', ['addresses' => $addresses]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $isDefault = $request->boolean('is_default');

        // Second independent sweep finding (P3, data integrity): nothing
        // enforced "at most one default address" — a buyer could end up
        // with several addresses simultaneously marked default. The
        // checkout flow always has the buyer pick an address explicitly
        // (never auto-selects "the" default), so this was a confusing
        // display bug, not a wrong-address-used-silently risk, but it's
        // still a real invariant worth keeping.
        if ($isDefault) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $request->user()->addresses()->create([...$data, 'is_default' => $isDefault]);

        return back()->with('status', 'Address saved.');
    }

    public function destroyAddress(Address $address): RedirectResponse
    {
        abort_unless($address->user_id === auth()->id(), 404);

        $address->delete();

        return back()->with('status', 'Address removed.');
    }

    public function wishlist(Request $request): View
    {
        return view('dashboard.buyer.wishlist', [
            'items' => $request->user()->wishlistItems()->with('product.store', 'product.images')->latest()->paginate(15),
        ]);
    }

    public function removeFromWishlist(Request $request, Product $product, WishlistService $wishlistService): RedirectResponse
    {
        $wishlistService->toggle($request->user(), $product);

        return back()->with('status', 'Removed from wishlist.');
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
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

        $request->user()->addresses()->create([...$data, 'is_default' => $request->boolean('is_default')]);

        return back()->with('status', 'Address saved.');
    }

    public function destroyAddress(Address $address): RedirectResponse
    {
        abort_unless($address->user_id === auth()->id(), 404);

        $address->delete();

        return back()->with('status', 'Address removed.');
    }
}

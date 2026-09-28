<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\OrderGroupShipment;
use App\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TDD §3.6 module 29 / Design System §6.11: assigned deliveries as a card
 * list (field use on mobile is primary — a table is the wrong shape
 * here).
 */
class ShipperController extends Controller
{
    public function index(Request $request): View
    {
        $shipper = $request->user()->shipper;
        abort_unless($shipper !== null, 403, 'This action requires a shipper account.');

        return view('dashboard.shipper.index', [
            'myShipments' => OrderGroupShipment::query()
                ->where('shipper_id', $shipper->id)
                ->whereNotIn('status', ['delivered', 'failed'])
                ->with('orderGroup.order', 'orderGroup.items.variant.product', 'zone')
                ->get(),
            'unclaimedShipments' => OrderGroupShipment::query()
                ->whereNull('shipper_id')
                ->whereNotIn('status', ['delivered', 'failed'])
                ->with('orderGroup.order', 'zone')
                ->get(),
        ]);
    }

    public function claim(Request $request, OrderGroupShipment $shipment, ShippingService $shippingService): RedirectResponse
    {
        $this->authorize('claim', $shipment);

        $shippingService->claim($shipment, $request->user()->shipper);

        return back()->with('status', 'Shipment claimed.');
    }

    public function storeEvent(Request $request, OrderGroupShipment $shipment, ShippingService $shippingService): RedirectResponse
    {
        $this->authorize('logEvent', $shipment);

        $data = $request->validate([
            'event_type' => ['required', 'in:picked_up,in_transit,out_for_delivery,delivered,failed,rescheduled'],
            'note' => ['nullable', 'string'],
            'photo' => ['required_if:event_type,delivered', 'image', 'max:10240'],
            'signature' => ['required_if:event_type,delivered', 'image', 'max:5120'],
        ]);

        $shippingService->recordEvent($shipment, $data['event_type'], $request->user(), $data);

        return back()->with('status', 'Delivery status updated.');
    }
}

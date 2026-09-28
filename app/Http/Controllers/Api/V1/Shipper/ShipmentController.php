<?php

namespace App\Http\Controllers\Api\V1\Shipper;

use App\Http\Controllers\Controller;
use App\Models\OrderGroupShipment;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shipper = $request->user()->shipper;
        abort_unless($shipper !== null, 403, 'This action requires a shipper account.');

        $shipments = OrderGroupShipment::query()
            ->where('shipper_id', $shipper->id)
            ->whereNotIn('status', ['delivered', 'failed'])
            ->with('orderGroup.order')
            ->get();

        return response()->json(['data' => $shipments]);
    }

    /**
     * TDD §3.4 module 23: claiming a pooled, platform-dispatched shipment.
     */
    public function claim(Request $request, OrderGroupShipment $shipment, ShippingService $shippingService): JsonResponse
    {
        $this->authorize('claim', $shipment);

        $shipper = $request->user()->shipper;
        abort_unless($shipper !== null, 403, 'This action requires a shipper account.');

        $shipment = $shippingService->claim($shipment, $shipper);

        return response()->json(['data' => $shipment]);
    }

    /**
     * TDD §7.3 endpoint table: "Log a shipment event
     * (pickup/transit/delivered)." Design System §6.11: proof-of-delivery
     * (photo + signature) is required before a delivery can be marked
     * complete.
     */
    public function storeEvent(Request $request, OrderGroupShipment $shipment, ShippingService $shippingService): JsonResponse
    {
        $this->authorize('logEvent', $shipment);

        $data = $request->validate([
            'event_type' => ['required', 'in:picked_up,in_transit,out_for_delivery,delivered,failed,rescheduled'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string'],
            'photo' => ['required_if:event_type,delivered', 'image', 'max:10240'],
            'signature' => ['required_if:event_type,delivered', 'image', 'max:5120'],
        ]);

        $shipment = $shippingService->recordEvent($shipment, $data['event_type'], $request->user(), $data);

        return response()->json(['data' => $shipment->load('events')], 201);
    }
}

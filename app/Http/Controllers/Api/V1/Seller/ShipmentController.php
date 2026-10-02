<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\OrderGroup;
use App\Models\Shipper;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    /**
     * TDD §3.4 module 22-23: the seller assigns a delivery zone/method
     * (validated against their own rate card) and optionally their own
     * courier; leaving shipper_id out dispatches to the pooled shipper
     * marketplace instead (App\Services\ShippingService::claim()).
     */
    public function store(Request $request, OrderGroup $orderGroup, ShippingService $shippingService): JsonResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'exists:delivery_zones,id'],
            'method' => ['required', 'in:standard,express,pickup'],
            'shipper_id' => ['nullable', 'exists:shippers,id'],
        ]);

        $zone = DeliveryZone::findOrFail($data['zone_id']);
        $shippingService->rateCardFor($orderGroup, $zone, $data['method']);

        $shipper = isset($data['shipper_id']) ? Shipper::findOrFail($data['shipper_id']) : null;

        $shipment = $shippingService->assign($orderGroup, $zone, $data['method'], $shipper);

        return response()->json(['data' => $shipment], 201);
    }
}

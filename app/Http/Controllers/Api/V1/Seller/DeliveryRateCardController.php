<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRateCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TDD §3.4 module 26: a seller opts into delivery zones by creating rate
 * cards for them — there is no separate "which zones do I serve" list.
 */
class DeliveryRateCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => DeliveryRateCard::with('zone')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'exists:delivery_zones,id'],
            'method' => ['required', 'in:standard,express,pickup'],
            'base_fee' => ['required', 'decimal:0,2', 'numeric', 'min:0'],
            'free_threshold' => ['nullable', 'decimal:0,2', 'numeric', 'min:0'],
            'eta_min_days' => ['required', 'integer', 'min:0'],
            'eta_max_days' => ['required', 'integer', 'gte:eta_min_days'],
            'pickup_address' => ['nullable', 'string'],
        ]);

        $rateCard = DeliveryRateCard::updateOrCreate(
            [
                'seller_id' => $request->user()->seller->id,
                'zone_id' => $data['zone_id'],
                'method' => $data['method'],
            ],
            collect($data)->except(['zone_id', 'method'])->merge(['enabled' => true])->all(),
        );

        return response()->json(['data' => $rateCard], 201);
    }
}

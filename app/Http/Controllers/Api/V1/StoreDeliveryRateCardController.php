<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRateCard;
use App\Models\Store;
use Illuminate\Http\JsonResponse;

/**
 * Mobile app groundwork: the web checkout's delivery step
 * (Storefront\CheckoutController::showDelivery()) resolves a store's
 * rate cards server-side to render as radio options — the JSON API had
 * no equivalent, leaving a mobile buyer no way to see what delivery
 * methods/fees a store even offers before submitting a selection to
 * CheckoutController::setDelivery(). Public/guest-accessible like
 * /products, since guest checkout (TDD §5.9) needs this too.
 */
class StoreDeliveryRateCardController extends Controller
{
    public function index(Store $store): JsonResponse
    {
        $rateCards = DeliveryRateCard::where('seller_id', $store->seller_id)
            ->where('enabled', true)
            ->with('zone')
            ->get();

        return response()->json(['data' => $rateCards]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Mobile app groundwork: the web dashboard's "my orders" page
     * (BuyerController::orders()) had no API equivalent — only a
     * single-order show() existed, with no way to list them.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user('sanctum')->orders()
            ->with('orderGroups')
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json(['data' => $order->load(
            'orderGroups.items.variant.product',
            'orderGroups.shipment.events',
            'payments',
        )]);
    }
}

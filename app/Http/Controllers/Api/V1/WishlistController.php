<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile app groundwork: the web dashboard's wishlist page
 * (BuyerController::wishlist()/removeFromWishlist()) and the storefront
 * wishlist-heart Livewire component both go through
 * App\Services\WishlistService — this is that same toggle/list logic's
 * JSON-API equivalent. Wishlists are buyer-only (TDD §6.2-adjacent: a
 * guest has nothing to attach one to), so this whole controller sits
 * behind auth:sanctum.
 */
class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user('sanctum')->wishlistItems()
            ->with('product.store', 'product.images')
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function toggle(Request $request, Product $product, WishlistService $wishlistService): JsonResponse
    {
        $added = $wishlistService->toggle($request->user('sanctum'), $product);

        return response()->json(['data' => ['wishlisted' => $added]]);
    }
}

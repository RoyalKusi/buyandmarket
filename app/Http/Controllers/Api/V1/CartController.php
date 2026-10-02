<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request, CartService $cartService): JsonResponse
    {
        $cart = $cartService->getOrCreateCart($request->user('sanctum'), $request->session()->getId());

        return response()->json(['data' => $cart->load('items.variant.product.store')]);
    }

    public function storeItem(Request $request, CartService $cartService): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $cartService->getOrCreateCart($request->user('sanctum'), $request->session()->getId());
        $variant = ProductVariant::findOrFail($data['variant_id']);

        $item = $cartService->addItem($cart, $variant, $data['quantity']);

        return response()->json(['data' => $item], 201);
    }

    public function updateItem(Request $request, CartItem $item, CartService $cartService): JsonResponse
    {
        $this->authorizeItem($request, $item);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0']]);

        $item = $cartService->updateQuantity($item, $data['quantity']);

        return response()->json(['data' => $item->exists ? $item : null]);
    }

    public function destroyItem(Request $request, CartItem $item, CartService $cartService): JsonResponse
    {
        $this->authorizeItem($request, $item);

        $cartService->removeItem($item);

        return response()->json(status: 204);
    }

    /**
     * Carts aren't looked up by an arbitrary owner-supplied ID anywhere
     * (always "my cart"), but a cart item's route-bound ID could still be
     * guessed — so ownership is checked explicitly here rather than
     * relying on a Policy class for what is a same-session/same-user
     * check, not a role-based authorization decision.
     */
    private function authorizeItem(Request $request, CartItem $item): void
    {
        $cart = $item->cart;
        $owns = $request->user('sanctum') !== null
            ? $cart->user_id === $request->user('sanctum')->id
            : $cart->session_id === $request->session()->getId();

        abort_unless($owns, 404);
    }
}

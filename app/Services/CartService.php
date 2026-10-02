<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TDD §3.4 module 17: persisted server-side for authenticated users,
 * session-based for guests, merged on login; line items snapshot price at
 * add-time with a re-validation pass at checkout.
 */
class CartService
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function getOrCreateCart(?User $user, ?string $sessionId): Cart
    {
        if ($user !== null) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    public function addItem(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        if ($variant->product->status !== 'published') {
            throw ValidationException::withMessages([
                'variant' => 'This product is not currently available.',
            ]);
        }

        $item = DB::transaction(function () use ($cart, $variant, $quantity) {
            $item = $cart->items()->firstOrNew(['variant_id' => $variant->id]);
            $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
            // Snapshot at add-time (TDD §3.4 module 17); re-validated
            // against the live price at checkout (CheckoutService).
            $item->price_snapshot = $variant->price();
            $item->save();

            return $item;
        });

        // TDD module 41: the analytics event stream, flagged deferred
        // since Run 1.7/1.11 — this is its "add_to_cart" signal.
        $this->analyticsService->record('add_to_cart', $variant->product, $cart->user, ['quantity' => $quantity]);

        return $item;
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        if ($quantity < 1) {
            $item->delete();

            return $item;
        }

        $item->update(['quantity' => $quantity]);

        return $item;
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * TDD §3.4 module 17: "merged on login." Guest cart items are added
     * into the user's persistent cart (quantities summed on collision),
     * then the now-empty guest cart is discarded.
     */
    public function mergeIntoUserCart(Cart $guestCart, User $user): Cart
    {
        return DB::transaction(function () use ($guestCart, $user) {
            $userCart = $this->getOrCreateCart($user, null);

            foreach ($guestCart->items as $guestItem) {
                $existing = $userCart->items()->where('variant_id', $guestItem->variant_id)->first();

                if ($existing !== null) {
                    $existing->update(['quantity' => $existing->quantity + $guestItem->quantity]);
                } else {
                    $userCart->items()->create([
                        'variant_id' => $guestItem->variant_id,
                        'quantity' => $guestItem->quantity,
                        'price_snapshot' => $guestItem->price_snapshot,
                    ]);
                }
            }

            $guestCart->delete();

            return $userCart;
        });
    }
}

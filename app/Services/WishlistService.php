<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;

/**
 * TDD §3.5-adjacent buyer feature — a plain per-user toggle, no separate
 * named lists (see the migration's own docblock for why).
 */
class WishlistService
{
    public function toggle(User $user, Product $product): bool
    {
        $existing = WishlistItem::where('user_id', $user->id)->where('product_id', $product->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        WishlistItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'created_at' => now()]);

        return true;
    }

    public function contains(User $user, Product $product): bool
    {
        return WishlistItem::where('user_id', $user->id)->where('product_id', $product->id)->exists();
    }
}

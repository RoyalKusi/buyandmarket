<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * TDD §8.5: a buyer sees only their own orders; a seller sees only the
 * order_groups belonging to them within a multi-seller order (enforced
 * separately by OrderGroup's own SellerOwned scope — this Policy governs
 * the parent Order/receipt view).
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($order->user_id === $user->id) {
            return true;
        }

        return $order->orderGroups()
            ->whereHas('seller', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}

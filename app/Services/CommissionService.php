<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\OrderGroup;

/**
 * TDD §7.4: "on succeeded, a commissions row is computed per order_group
 * (seller's tier-based commission rate × order_group subtotal)." Seller
 * commission tiers don't exist yet (no module builds them) — this uses a
 * single platform-wide default rate from config until a tiering module
 * arrives, a deliberate simplification, not a silent omission of the
 * tiering concept itself (the rate_applied column already exists per
 * order_group precisely so tiering can vary it later without a schema
 * change).
 */
class CommissionService
{
    public function recordForOrderGroup(OrderGroup $orderGroup): Commission
    {
        // See OrderService::createFromCheckoutSession for why this uses
        // plain float arithmetic instead of bcmath.
        $rate = (float) config('commerce.default_commission_rate', '0.10');
        $amount = round((float) $orderGroup->subtotal * $rate, 2);

        $orderGroup->update(['commission_amount' => $amount]);

        return Commission::create([
            'order_group_id' => $orderGroup->id,
            'rate_applied' => $rate,
            'amount' => $amount,
        ]);
    }
}

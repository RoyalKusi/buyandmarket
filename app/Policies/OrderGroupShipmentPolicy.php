<?php

namespace App\Policies;

use App\Models\OrderGroupShipment;
use App\Models\User;

/**
 * TDD §7.3: shipper endpoints are "assignment-scoped" — a shipper may only
 * log events against a shipment claimed/assigned to them. Sellers manage
 * (assign) shipments for their own order_groups, enforced separately at
 * the OrderGroup level (SellerOwned + ScopeQueriesToActingSeller) before a
 * shipment is even created.
 */
class OrderGroupShipmentPolicy
{
    public function logEvent(User $user, OrderGroupShipment $shipment): bool
    {
        return $shipment->shipper !== null && $shipment->shipper->user_id === $user->id;
    }

    public function claim(User $user, OrderGroupShipment $shipment): bool
    {
        return $shipment->isUnclaimed();
    }
}

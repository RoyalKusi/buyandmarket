<?php

namespace App\Policies;

use App\Models\Shipper;
use App\Models\User;

/**
 * TDD §3.1-style self-service registration, mirrored for the shipper role
 * (§3.4 module 23): registration is self-service, every other action is
 * owner-only.
 */
class ShipperPolicy
{
    public function register(User $user): bool
    {
        return $user->shipper === null;
    }

    public function manage(User $user, Shipper $shipper): bool
    {
        return $user->id === $shipper->user_id;
    }
}

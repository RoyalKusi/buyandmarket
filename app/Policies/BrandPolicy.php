<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

/**
 * TDD §3.2 module 11: any active seller may suggest a brand; only an
 * admin may approve or reject one (via Gate::before).
 */
class BrandPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('seller') && $user->seller?->isActive();
    }

    public function approve(User $user, Brand $brand): bool
    {
        return false;
    }

    public function reject(User $user, Brand $brand): bool
    {
        return false;
    }
}

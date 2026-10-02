<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * TDD §3.2 module 10: category tree management is an admin-only surface.
 * Every method returns false — access is granted exclusively by the
 * admin Gate::before bypass (App\Providers\AppServiceProvider).
 */
class CategoryPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Category $category): bool
    {
        return false;
    }
}

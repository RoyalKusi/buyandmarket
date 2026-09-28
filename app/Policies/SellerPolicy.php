<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\User;

/**
 * TDD §3.1: registration is self-service (any user may become a seller of
 * their own account); every other onboarding action is owner-only; KYC
 * review is admin-only (via the Gate::before bypass, App\Providers\
 * AppServiceProvider).
 */
class SellerPolicy
{
    public function register(User $user): bool
    {
        return $user->seller === null;
    }

    public function manage(User $user, Seller $seller): bool
    {
        return $user->id === $seller->user_id;
    }

    public function review(User $user, Seller $seller): bool
    {
        return false;
    }

    public function viewKycDocuments(User $user, Seller $seller): bool
    {
        return $this->manage($user, $seller);
    }
}

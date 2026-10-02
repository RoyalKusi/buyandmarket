<?php

namespace App\Policies;

use App\Models\SponsoredCampaign;
use App\Models\User;

/**
 * TDD module 15: any active seller may submit a campaign for their own
 * product; only an admin may approve or reject one (via Gate::before) —
 * same moderation shape as App\Policies\BrandPolicy.
 */
class SponsoredCampaignPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('seller') && $user->seller?->isActive();
    }

    public function manage(User $user, SponsoredCampaign $campaign): bool
    {
        return $user->seller !== null && $user->seller->id === $campaign->seller_id;
    }

    public function approve(User $user, SponsoredCampaign $campaign): bool
    {
        return false;
    }

    public function reject(User $user, SponsoredCampaign $campaign): bool
    {
        return false;
    }
}

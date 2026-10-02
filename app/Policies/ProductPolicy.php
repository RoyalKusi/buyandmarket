<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * TDD §8.1: "the single point of truth for 'can this actor do this to this
 * resource'." Admin's unconditional access comes from the Gate::before
 * bypass (App\Providers\AppServiceProvider) — every method here encodes
 * only the seller-facing rule.
 */
class ProductPolicy
{
    public function view(?User $user, Product $product): bool
    {
        return $product->status === 'published' || ($user !== null && $this->isOwner($user, $product));
    }

    public function create(User $user): bool
    {
        // TDD §3.1 module 4: the onboarding stepper's "first product" step
        // happens before a seller reaches 'active' status — so creating a
        // draft product only requires being a seller in good standing, not
        // an active one. "Only active sellers can list products" (§3.1
        // module 2) is enforced instead where it actually applies: the
        // pending_review -> published transition (see ProductService::
        // approve() and this policy's approve()).
        return $user->hasRole('seller') && $user->seller?->isInGoodStanding();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->isOwner($user, $product);
    }

    public function submitForReview(User $user, Product $product): bool
    {
        return $this->isOwner($user, $product);
    }

    public function archive(User $user, Product $product): bool
    {
        return $this->isOwner($user, $product);
    }

    public function manageImages(User $user, Product $product): bool
    {
        return $this->isOwner($user, $product);
    }

    /**
     * Only an admin may move a product out of moderation — never the
     * seller who submitted it (Gate::before is the only path to true).
     */
    public function approve(User $user, Product $product): bool
    {
        return false;
    }

    public function reject(User $user, Product $product): bool
    {
        return false;
    }

    private function isOwner(User $user, Product $product): bool
    {
        return $user->seller !== null && $user->seller->id === $product->store->seller_id;
    }
}

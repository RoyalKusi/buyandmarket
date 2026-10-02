<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;

/**
 * TDD module 33: any buyer with a verified purchase may review a
 * product, once; only an admin may remove a review (via Gate::before —
 * every other actor, including the review's own author, gets false).
 */
class ReviewPolicy
{
    public function __construct(private readonly ReviewService $reviewService) {}

    public function create(User $user, Product $product): bool
    {
        return $this->reviewService->canReview($product, $user);
    }

    public function remove(User $user, Review $review): bool
    {
        return false;
    }
}

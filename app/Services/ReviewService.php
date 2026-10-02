<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * TDD module 33: reviews are gated on a verified purchase — `order_id`
 * is checked, not just recorded, before a review can be written at all.
 */
class ReviewService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function canReview(Product $product, User $user): bool
    {
        return $this->verifiedOrderFor($product, $user) !== null
            && ! Review::where('product_id', $product->id)->where('user_id', $user->id)->exists();
    }

    public function create(Product $product, User $user, int $rating, ?string $title, string $body): Review
    {
        $order = $this->verifiedOrderFor($product, $user);

        if ($order === null) {
            throw ValidationException::withMessages([
                'product' => 'You can only review a product you have bought.',
            ]);
        }

        if (Review::where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'product' => 'You have already reviewed this product.',
            ]);
        }

        return Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
        ]);
    }

    public function remove(Review $review, User $admin, string $reasonCode): Review
    {
        $before = $review->only(['status']);
        $review->update(['status' => 'removed']);

        $this->auditLogger->log(
            actor: $admin,
            action: 'review.removed',
            subject: $review,
            before: $before,
            after: [...$review->only(['status']), 'reason_code' => $reasonCode],
        );

        return $review;
    }

    private function verifiedOrderFor(Product $product, User $user): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereHas(
                'orderGroups.items.variant',
                fn ($query) => $query->where('product_id', $product->id)
            )
            ->first();
    }
}

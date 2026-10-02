<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * TDD §6.1's homepage/PDP rails ("Deals near you", AI-curated "Picked
 * for you", related/recently-viewed — module 40), flagged deferred since
 * Run 1.4 for lack of view-tracking data and, for "Picked for you",
 * an AI/RAG module to curate it. Both rails here are deterministic —
 * same discipline as the seller dashboard's pricing insights and
 * low-stock alerts (CHANGELOG.md, Run 1.7): a recommendation a buyer can
 * trust the reasoning behind ("bought in a category you've bought from
 * before") beats an LLM narrating a guess it can't actually ground in
 * real signal.
 */
class RecommendationService
{
    private const LIMIT = 8;

    /**
     * @return Collection<int, Product>
     */
    public function relatedTo(Product $product): Collection
    {
        return Product::query()
            ->published()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->with('store')
            ->latest()
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * "Picked for you": category affinity from the buyer's own
     * confirmed/completed order history, excluding products already
     * bought. A guest, or a buyer with no order history yet, gets the
     * same fallback as the homepage's other sections — the newest
     * published listings — rather than an empty rail.
     *
     * @return Collection<int, Product>
     */
    public function pickedFor(?User $user): Collection
    {
        $categoryIds = $user === null ? collect() : $this->purchasedCategoryIds($user);

        if ($categoryIds->isEmpty()) {
            return Product::query()->published()->with('store')->latest()->limit(self::LIMIT)->get();
        }

        $purchasedProductIds = $this->purchasedProductIds($user);

        return Product::query()
            ->published()
            ->whereIn('category_id', $categoryIds)
            ->whereNotIn('id', $purchasedProductIds)
            ->with('store')
            ->latest()
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function purchasedCategoryIds(User $user): Collection
    {
        return Product::query()
            ->whereIn('id', $this->purchasedProductIds($user))
            ->pluck('category_id')
            ->unique();
    }

    /**
     * @return Collection<int, int>
     */
    private function purchasedProductIds(User $user): Collection
    {
        return OrderItem::query()
            ->whereHas('orderGroup.order', fn ($query) => $query->where('user_id', $user->id)->whereIn('status', ['confirmed', 'completed']))
            ->with('variant')
            ->get()
            ->pluck('variant.product_id')
            ->unique();
    }
}

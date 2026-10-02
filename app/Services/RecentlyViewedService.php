<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * TDD §6.1/module 40's "recently viewed" rail — session-based (works
 * identically for a guest or a signed-in buyer, same guest-first
 * posture the cart already takes), not a persisted view-event table:
 * no analytics event stream exists yet (flagged since Run 1.11's
 * CHANGELOG) to back anything more durable than "this browser session."
 */
class RecentlyViewedService
{
    private const SESSION_KEY = 'recently_viewed_product_ids';

    private const MAX_ITEMS = 10;

    public function record(Product $product): void
    {
        $ids = $this->ids()->reject(fn ($id) => $id === $product->id)->prepend($product->id);

        session()->put(self::SESSION_KEY, $ids->take(self::MAX_ITEMS)->values()->all());
    }

    /**
     * @return Collection<int, Product>
     */
    public function recentlyViewed(?Product $excluding = null): Collection
    {
        $ids = $this->ids()->reject(fn ($id) => $excluding !== null && $id === $excluding->id);

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::query()->published()->whereIn('id', $ids)->with('store')->get()->keyBy('id');

        return $ids->map(fn ($id) => $products->get($id))->filter()->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function ids(): Collection
    {
        return collect(session()->get(self::SESSION_KEY, []));
    }
}

<?php

namespace App\Services\Search;

use App\Contracts\SearchProvider;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * TDD §2.1: "MySQL full-text (FULLTEXT indexes + relevance scoring)
 * initially." Uses whereFullText() (MATCH...AGAINST against the index
 * products' migration adds) only on MySQL — Eloquent's own grammar
 * throws for any driver whose grammar doesn't implement it (SQLite,
 * which this app's tests run on per phpunit.xml, is one of them), so a
 * portable LIKE-based fallback keeps the same call site working
 * everywhere without a second SearchProvider implementation.
 *
 * TDD §5.2: "Retrieval runs a metadata-filtered vector search first (only
 * active sellers, published products...)" — the AI/RAG pipeline (Run 1.8)
 * is a different, semantic retrieval path, but the *visibility* filter
 * here is the same rule: only published products, from a store whose
 * seller is active, are ever returned to a searching buyer.
 */
class EloquentSearchProvider implements SearchProvider
{
    public function search(
        ?string $query,
        array $filters = [],
        string $sort = 'relevance',
        int $perPage = 24,
    ): LengthAwarePaginator {
        $builder = Product::query()
            ->published()
            ->whereHas('store.seller', fn (Builder $q) => $q->where('status', 'active'));

        if ($query !== null && $query !== '') {
            if (DB::connection()->getDriverName() === 'mysql') {
                $builder->whereFullText(['title', 'description'], $query);
            } else {
                $builder->where(fn (Builder $q) => $q
                    ->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%"));
            }
        }

        if (isset($filters['category_id'])) {
            $builder->where('category_id', $filters['category_id']);
        }

        if (isset($filters['store_id'])) {
            $builder->where('store_id', $filters['store_id']);
        }

        if (isset($filters['brand_id'])) {
            $builder->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['min_price'])) {
            $builder->where('base_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $builder->where('base_price', '<=', $filters['max_price']);
        }

        match ($sort) {
            'price_low_high' => $builder->orderBy('base_price', 'asc'),
            'price_high_low' => $builder->orderBy('base_price', 'desc'),
            'newest' => $builder->orderBy('created_at', 'desc'),
            // 'relevance' with no query term, and any sort this provider
            // doesn't specifically recognise, falls back to newest-first —
            // there is no meaningful relevance ranking without a query.
            default => $builder->orderBy('created_at', 'desc'),
        };

        return $builder->with(['store', 'variants'])->paginate($perPage)->withQueryString();
    }
}

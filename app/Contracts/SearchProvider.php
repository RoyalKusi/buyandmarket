<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * TDD §2.1 / §9.7 stage 4: MySQL full-text now, swappable to a
 * Meilisearch/Typesense adapter later with zero call-site changes.
 * Nothing outside App\Services\Search may query the catalogue for
 * search/browse purposes without going through this contract.
 */
interface SearchProvider
{
    /**
     * @param  array{category_id?: int, brand_id?: int, store_id?: int, min_price?: string, max_price?: string}  $filters
     */
    public function search(
        ?string $query,
        array $filters = [],
        string $sort = 'relevance',
        int $perPage = 24,
    ): LengthAwarePaginator;
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SearchProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile app groundwork: the web storefront's browse/search/category
 * pages all go through one Livewire component (App\Livewire\ProductGrid)
 * calling App\Contracts\SearchProvider — this is that same contract's
 * JSON-API equivalent, so the mobile client's home/search/category
 * screens share the exact same catalogue-visibility rules (published
 * products, active seller only) and filter/sort behaviour as the web.
 */
class ProductSearchController extends Controller
{
    public function index(Request $request, SearchProvider $searchProvider): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            // Matches App\Services\Search\EloquentSearchProvider's own
            // recognised sort keys exactly — the same ones the web
            // storefront's ProductGrid Livewire component sends.
            'sort' => ['nullable', 'string', 'in:relevance,price_low_high,price_high_low,newest'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = array_filter([
            'category_id' => $data['category_id'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'min_price' => $data['min_price'] ?? null,
            'max_price' => $data['max_price'] ?? null,
        ], fn ($value) => $value !== null);

        $results = $searchProvider->search(
            query: $data['q'] ?? null,
            filters: $filters,
            sort: $data['sort'] ?? 'relevance',
            perPage: 24,
        );

        // EloquentSearchProvider already eager-loads ->with(['store',
        // 'variants']) internally — no need to repeat it here.
        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'total' => $results->total(),
            ],
        ]);
    }
}

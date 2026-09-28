<?php

namespace App\Livewire;

use App\Contracts\SearchProvider;
use App\Models\Brand;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Design System §6.3: "a category page is architecturally 'search
 * pre-scoped to one category' — one component set serves both, reducing
 * implementation surface and keeping the experience consistent." This
 * component is that shared piece; SearchController and CategoryController
 * both mount it, the latter with $categoryId locked and hidden from the
 * filter UI.
 */
class ProductGrid extends Component
{
    use WithPagination;

    #[Url]
    public ?string $q = null;

    public ?int $categoryId = null;

    public bool $categoryLocked = false;

    public ?int $storeId = null;

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public ?string $minPrice = null;

    #[Url]
    public ?string $maxPrice = null;

    #[Url]
    public string $sort = 'relevance';

    public function mount(?string $q = null, ?int $categoryId = null, ?int $storeId = null): void
    {
        $this->q = $q;
        $this->storeId = $storeId;

        if ($categoryId !== null) {
            $this->categoryId = $categoryId;
            $this->categoryLocked = true;
        }
    }

    public function updating($property): void
    {
        if (in_array($property, ['q', 'brandId', 'minPrice', 'maxPrice', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->brandId = null;
        $this->minPrice = null;
        $this->maxPrice = null;
        $this->sort = 'relevance';
        $this->resetPage();
    }

    public function render()
    {
        $filters = array_filter([
            'category_id' => $this->categoryId,
            'store_id' => $this->storeId,
            'brand_id' => $this->brandId,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
        ], fn ($value) => $value !== null && $value !== '');

        $results = app(SearchProvider::class)->search(
            query: $this->q,
            filters: $filters,
            sort: $this->sort,
            perPage: 24,
        );

        return view('livewire.product-grid', [
            'results' => $results,
            'brands' => Brand::query()->where('status', 'approved')->orderBy('name')->get(),
            'activeFilterCount' => collect([$this->brandId, $this->minPrice, $this->maxPrice])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->count(),
        ]);
    }
}

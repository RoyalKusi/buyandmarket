{{--
    Design System §6.2: left filter rail (desktop, 280px, sticky) / a
    mobile filter drawer is deferred — this run ships the filters inline
    above the grid on every breakpoint rather than a half-built bottom
    sheet. Price range here is two plain number inputs, not the dual-
    handle slider (§6.2) — that's a JS-heavy component better built once
    Alpine patterns are established elsewhere in the storefront.
--}}
<div wire:loading.class="opacity-60" class="transition-opacity duration-fast">
    <div class="flex flex-wrap items-end gap-4 mb-6 pb-6 border-b border-slate-100">
        @unless ($categoryLocked)
            <p class="text-body-sm text-slate-500">Showing all categories</p>
        @endunless

        <div class="flex flex-col gap-1">
            <label for="pg-brand" class="text-body-md text-slate-700">Brand</label>
            <select id="pg-brand" wire:model.live="brandId" class="h-11 rounded-sm border border-slate-200 px-3 text-body-lg">
                <option value="">All brands</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="pg-min-price" class="text-body-md text-slate-700">Min price</label>
            <input id="pg-min-price" type="number" min="0" step="0.01" wire:model.live.debounce.400ms="minPrice" class="h-11 w-28 rounded-sm border border-slate-200 px-3 text-body-lg">
        </div>

        <div class="flex flex-col gap-1">
            <label for="pg-max-price" class="text-body-md text-slate-700">Max price</label>
            <input id="pg-max-price" type="number" min="0" step="0.01" wire:model.live.debounce.400ms="maxPrice" class="h-11 w-28 rounded-sm border border-slate-200 px-3 text-body-lg">
        </div>

        <div class="flex flex-col gap-1">
            <label for="pg-sort" class="text-body-md text-slate-700">Sort</label>
            <select id="pg-sort" wire:model.live="sort" class="h-11 rounded-sm border border-slate-200 px-3 text-body-lg">
                <option value="relevance">Relevance</option>
                <option value="price_low_high">Price: low to high</option>
                <option value="price_high_low">Price: high to low</option>
                <option value="newest">Newest</option>
            </select>
        </div>

        @if ($activeFilterCount > 0)
            <button type="button" wire:click="clearFilters" class="h-11 px-4 text-body-md text-blue-600 hover:underline">
                Clear filters ({{ $activeFilterCount }})
            </button>
        @endif
    </div>

    <p class="text-body-sm text-slate-500 mb-4">{{ $results->total() }} {{ Str::plural('result', $results->total()) }}</p>

    <div wire:loading.remove>
        @if ($results->isEmpty())
            <x-empty-state
                heading="No products found"
                message="Try a different search term, or adjust your filters."
                action-label="Browse categories"
                :action-href="route('storefront.home')"
            />
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-5">
                @foreach ($results as $product)
                    <x-product-tile :product="$product" wire:key="product-{{ $product->id }}" />
                @endforeach
            </div>

            <div class="mt-8">
                {{ $results->links() }}
            </div>
        @endif
    </div>

    <div wire:loading class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-5">
        @for ($i = 0; $i < 8; $i++)
            <x-skeleton.product-tile />
        @endfor
    </div>
</div>

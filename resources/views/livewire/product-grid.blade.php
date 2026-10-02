{{--
    Design System §6.2: left filter rail (desktop, 280px, sticky) / a
    mobile filter drawer is deferred — this run ships the filters inline
    above the grid on every breakpoint rather than a half-built bottom
    sheet. The dual-handle price slider (§6.2), flagged deferred since
    Run 1.4, shipped in Run 1.21 — two overlaid range inputs is the
    standard dependency-free technique, paired with number inputs for
    precise entry (Alpine keeps the two in sync; the Livewire
    wire:model.live.debounce is what actually re-queries).
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

        <div
            class="flex flex-col gap-1 w-full sm:w-auto"
            x-data="{
                min: {{ $minPrice !== null && $minPrice !== '' ? (float) $minPrice : 0 }},
                max: {{ $maxPrice !== null && $maxPrice !== '' ? (float) $maxPrice : $priceCeiling }},
                ceiling: {{ $priceCeiling }},
                clampMin() { this.min = Math.min(this.min, this.max); },
                clampMax() { this.max = Math.max(this.max, this.min); },
            }"
        >
            <label class="text-body-md text-slate-700">Price range</label>

            <div class="relative h-11 w-full sm:w-64 flex items-center">
                <div class="absolute inset-x-0 h-1 rounded-full bg-slate-200"></div>
                <div class="absolute h-1 rounded-full bg-blue-600" :style="`left: ${(min / ceiling) * 100}%; right: ${100 - (max / ceiling) * 100}%`"></div>
                <input type="range" min="0" :max="ceiling" step="1" x-model.number="min" @input="clampMin()"
                    @change="$wire.set('minPrice', min)"
                    class="absolute w-full appearance-none bg-transparent pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-blue-600 [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-blue-600 [&::-moz-range-thumb]:border-0">
                <input type="range" min="0" :max="ceiling" step="1" x-model.number="max" @input="clampMax()"
                    @change="$wire.set('maxPrice', max)"
                    class="absolute w-full appearance-none bg-transparent pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-blue-600 [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-blue-600 [&::-moz-range-thumb]:border-0">
            </div>

            <div class="flex items-center gap-2 text-body-sm text-slate-600">
                <span>$</span>
                <input type="number" min="0" :max="ceiling" x-model.number="min" @change="clampMin(); $wire.set('minPrice', min)" class="w-16 h-8 rounded-sm border border-slate-200 px-2">
                <span>&ndash;</span>
                <span>$</span>
                <input type="number" min="0" :max="ceiling" x-model.number="max" @change="clampMax(); $wire.set('maxPrice', max)" class="w-16 h-8 rounded-sm border border-slate-200 px-2">
            </div>
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

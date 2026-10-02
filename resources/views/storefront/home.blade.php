<x-layouts.storefront title="BuyAndMarket — Zimbabwe's multi-vendor marketplace">
    {{--
        Design System §6.1 homepage order. Omitted (flagged in
        CHANGELOG.md): hero carousel (needs curated campaign-banner
        content), "Deals near you" (geolocation ranking), sponsored
        placement block, trust strip. "Picked for you" and "Recently
        viewed" (module 40, Run 1.19) are wired below, deterministically
        — see App\Services\RecommendationService.
    --}}
    <div class="mx-auto max-w-[1280px] px-4 md:px-6 py-8 space-y-12">
        @if ($recentlyViewed->isNotEmpty())
            <section aria-labelledby="recently-viewed-heading">
                <h2 id="recently-viewed-heading" class="text-heading-lg text-slate-900 mb-4">Recently viewed</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach ($recentlyViewed as $product)
                        <x-product-tile :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-labelledby="categories-heading">
            <h2 id="categories-heading" class="text-heading-lg text-slate-900 mb-4">Shop by category</h2>

            @if ($categories->isEmpty())
                <p class="text-body-md text-slate-500">Categories are coming soon.</p>
            @else
                <div class="flex gap-4 overflow-x-auto pb-2 snap-x">
                    @foreach ($categories as $category)
                        <a
                            href="{{ route('storefront.categories.show', $category) }}"
                            class="shrink-0 snap-start flex flex-col items-center gap-2 w-20"
                        >
                            <span class="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center text-heading-md text-blue-600 font-display" aria-hidden="true">
                                {{ Str::of($category->name)->substr(0, 1)->upper() }}
                            </span>
                            <span class="text-body-sm text-slate-700 text-center line-clamp-2">{{ $category->name }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section aria-labelledby="stores-heading">
            <h2 id="stores-heading" class="text-heading-lg text-slate-900 mb-4">Featured stores</h2>

            @if ($stores->isEmpty())
                <p class="text-body-md text-slate-500">No stores are live yet.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach ($stores as $store)
                        <x-store-card :store="$store" />
                    @endforeach
                </div>
            @endif
        </section>

        @if ($pickedForYou->isNotEmpty())
            <section aria-labelledby="picked-for-you-heading">
                <h2 id="picked-for-you-heading" class="text-heading-lg text-slate-900 mb-4">Picked for you</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach ($pickedForYou as $product)
                        <x-product-tile :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.storefront>

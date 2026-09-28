<x-layouts.storefront :title="$product->title" :description="Str::limit($product->description, 160)">
    {{--
        Design System §6.4 (PDP). Omitted here (flagged in CHANGELOG.md,
        Run 1.4): the real image gallery (module 5.8, Run 1.9), rating
        row and reviews tab content (module 33), related/recently-viewed
        rails (module 40), the AI "Ask about this product" entry point
        (Run 1.8), and working Add to cart / Buy now (Run 1.5) — both
        CTAs render per spec but are disabled with an explanatory label,
        never silently inert.
    --}}
    <div class="mx-auto max-w-[1280px] px-4 md:px-6 py-8">
        <x-breadcrumbs :items="$breadcrumbs" />

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-[55fr_45fr] gap-8">
            <div class="aspect-square bg-slate-25 rounded-md flex items-center justify-center" aria-hidden="true">
                <span class="text-display-xl text-slate-300 font-display">{{ Str::of($product->title)->substr(0, 1)->upper() }}</span>
            </div>

            <div>
                <h1 class="text-heading-lg text-slate-900">{{ $product->title }}</h1>

                <a href="{{ route('storefront.stores.show', $product->store) }}" class="mt-2 inline-flex items-center gap-1 text-body-md text-slate-600 hover:text-blue-600">
                    {{ $product->store->name }}
                </a>

                <p class="mt-4 text-price-lg text-blue-600">${{ number_format((float) $product->base_price, 2) }}</p>

                @if ($product->variants->isNotEmpty())
                    <div class="mt-6">
                        <p class="text-body-md text-slate-700 mb-2">Options</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($product->variants as $variant)
                                <span class="h-10 px-4 rounded-full border border-slate-200 flex items-center text-body-sm text-slate-700">
                                    {{ $variant->sku }}
                                    @if ($variant->stock_quantity < 1)
                                        <span class="ml-2 text-danger-600">(out of stock)</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <p class="mt-4 text-body-sm text-slate-600">
                    {{ $product->stock_quantity > 0 ? "{$product->stock_quantity} in stock" : 'Out of stock' }}
                </p>

                <div class="mt-6 flex flex-col gap-2">
                    <button type="button" disabled class="h-12 rounded-sm border border-slate-200 text-slate-400 text-button cursor-not-allowed" title="Cart is not available yet">
                        Add to cart
                    </button>
                    <button type="button" disabled class="h-12 rounded-sm bg-slate-200 text-slate-400 text-button cursor-not-allowed" title="Checkout is not available yet">
                        Buy now
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-12 border-t border-slate-100 pt-8">
            <h2 class="text-heading-md text-slate-900 mb-3">Description</h2>
            <p class="text-body-lg text-slate-700 whitespace-pre-line">{{ $product->description ?: 'No description provided.' }}</p>
        </div>

        @if ($product->variants->flatMap->attributeValues->isNotEmpty())
            <div class="mt-8 border-t border-slate-100 pt-8">
                <h2 class="text-heading-md text-slate-900 mb-3">Specifications</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2">
                    @foreach ($product->variants->flatMap->attributeValues->unique('id') as $value)
                        <div class="flex justify-between border-b border-slate-100 py-2">
                            <dt class="text-body-md text-slate-500">{{ $value->attribute->name }}</dt>
                            <dd class="text-body-md text-slate-900">{{ $value->value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        <div class="mt-8 border-t border-slate-100 pt-8">
            <h2 class="text-heading-md text-slate-900 mb-3">Seller information</h2>
            <x-store-card :store="$product->store" />
        </div>
    </div>
</x-layouts.storefront>

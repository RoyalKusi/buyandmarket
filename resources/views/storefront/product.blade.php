<x-layouts.storefront :title="$product->title" :description="Str::limit($product->description, 160)">
    {{--
        Design System §6.4 (PDP). Omitted here (flagged in CHANGELOG.md,
        Run 1.4): the real image gallery (module 5.8, Run 1.9), rating
        row and reviews tab content (module 33), related/recently-viewed
        rails (module 40). Add to cart / Buy now (Run 1.12) and the AI
        "Ask about this product" entry point (Run 1.12) are wired.
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

                <p class="mt-4 text-body-sm text-slate-600">
                    {{ $product->stock_quantity > 0 ? "{$product->stock_quantity} in stock" : 'Out of stock' }}
                </p>

                {{-- Variant options are the interactive picker inside
                     this component (duplicating a static list above it
                     would just be confusing). --}}
                <livewire:storefront.add-to-cart-form :product="$product" />

                {{-- Design System §6.4 "Ask about this product" AI entry
                     point (TDD §7.1), pre-seeded with this product's
                     context (App\Services\Ai\AssistantService already
                     accepts context_type/context_id, wired end to end
                     here for the first time). --}}
                <a href="{{ route('dashboard.assistant', ['product' => $product->id]) }}" class="mt-3 block text-center text-body-sm text-blue-600 hover:underline">
                    Ask BM Assistant about this product
                </a>
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

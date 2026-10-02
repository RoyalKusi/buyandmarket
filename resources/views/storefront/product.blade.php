<x-layouts.storefront :title="$product->title" :description="Str::limit($product->description, 160)">
    {{--
        Design System §6.4 (PDP). The image gallery (TDD §5.8, Run 1.15),
        Add to cart / Buy now (Run 1.12), the AI "Ask about this product"
        entry point (Run 1.12), and the rating row/reviews tab (module
        33, Run 1.17) are wired. Still omitted (flagged in
        CHANGELOG.md): related/recently-viewed rails (module 40).
    --}}
    <div class="mx-auto max-w-[1280px] px-4 md:px-6 py-8">
        <x-breadcrumbs :items="$breadcrumbs" />

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-[55fr_45fr] gap-8">
            @if ($product->images->isNotEmpty())
                <div x-data="{ active: '{{ $product->primaryImage()?->id }}' }">
                    <div class="aspect-square bg-slate-25 rounded-md overflow-hidden">
                        @foreach ($product->images as $image)
                            <img x-show="active === '{{ $image->id }}'" src="{{ $image->variantUrl('large') }}" alt="{{ $image->alt_text ?: $product->title }}" class="w-full h-full object-contain">
                        @endforeach
                    </div>

                    @if ($product->images->count() > 1)
                        <div class="mt-3 grid grid-cols-5 gap-2">
                            @foreach ($product->images as $image)
                                <button type="button" @click="active = '{{ $image->id }}'"
                                    class="aspect-square rounded-sm overflow-hidden border-2"
                                    :class="active === '{{ $image->id }}' ? 'border-blue-600' : 'border-transparent'">
                                    <img src="{{ $image->variantUrl('thumb') }}" alt="" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="aspect-square bg-slate-25 rounded-md flex items-center justify-center" aria-hidden="true">
                    <span class="text-display-xl text-slate-300 font-display">{{ Str::of($product->title)->substr(0, 1)->upper() }}</span>
                </div>
            @endif

            <div>
                <h1 class="text-heading-lg text-slate-900">{{ $product->title }}</h1>

                <a href="{{ route('storefront.stores.show', $product->store) }}" class="mt-2 inline-flex items-center gap-1 text-body-md text-slate-600 hover:text-blue-600">
                    {{ $product->store->name }}
                </a>

                @if ($reviewStats['count'] > 0)
                    <a href="#reviews" class="mt-2 flex items-center gap-1 text-body-sm text-slate-600 hover:text-blue-600">
                        <span class="text-amber-500">&#9733;</span>
                        <span>{{ $reviewStats['average'] }} ({{ $reviewStats['count'] }} {{ Str::plural('review', $reviewStats['count']) }})</span>
                    </a>
                @endif

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

        <div id="reviews" class="mt-8 border-t border-slate-100 pt-8">
            <h2 class="text-heading-md text-slate-900 mb-3">
                Reviews
                @if ($reviewStats['count'] > 0)
                    <span class="text-body-md text-slate-500 font-normal">({{ $reviewStats['average'] }} average, {{ $reviewStats['count'] }} {{ Str::plural('review', $reviewStats['count']) }})</span>
                @endif
            </h2>

            @if ($canReview)
                <form method="POST" action="{{ route('storefront.products.reviews.store', $product) }}" class="bg-slate-25 rounded-md p-4 mb-6 space-y-3 max-w-xl">
                    @csrf
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Your rating</label>
                        <select name="rating" required class="h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                            @foreach ([5, 4, 3, 2, 1] as $stars)
                                <option value="{{ $stars }}">{{ $stars }} star{{ $stars > 1 ? 's' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Title (optional)</label>
                        <input type="text" name="title" class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Your review</label>
                        <textarea name="body" rows="3" required class="w-full rounded-sm border border-slate-200 px-3 py-2 text-body-md"></textarea>
                    </div>
                    <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Submit review</button>
                </form>
            @endif

            @forelse ($product->reviews as $review)
                <div class="border-b border-slate-100 py-4">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-500">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                        <span class="text-body-sm text-slate-500">{{ $review->user->name }} &middot; {{ $review->created_at->format('d M Y') }}</span>
                    </div>
                    @if ($review->title)
                        <p class="text-body-md font-semibold text-slate-900 mt-1">{{ $review->title }}</p>
                    @endif
                    <p class="text-body-md text-slate-700 mt-1">{{ $review->body }}</p>
                </div>
            @empty
                <p class="text-body-md text-slate-500">No reviews yet.</p>
            @endforelse
        </div>

        <div class="mt-8 border-t border-slate-100 pt-8">
            <h2 class="text-heading-md text-slate-900 mb-3">Seller information</h2>
            <x-store-card :store="$product->store" />
        </div>
    </div>
</x-layouts.storefront>

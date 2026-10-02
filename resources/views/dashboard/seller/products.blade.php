<x-layouts.dashboard title="Products" active="seller.products">
    <div class="mb-4">
        <a href="{{ route('seller.dashboard.products.create') }}" class="inline-block h-10 leading-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">+ Create a product</a>
    </div>

    @if ($products->isEmpty())
        <p class="text-body-lg text-slate-500">No products yet. Create your first one above.</p>
    @else
        <div class="space-y-4">
            @foreach ($products as $product)
                @php($stats = $categoryPriceStats->get($product->category_id))
                <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h2 class="text-heading-sm font-display text-slate-900">{{ $product->title }}</h2>
                            <p class="text-body-sm text-slate-500">
                                ${{ number_format((float) $product->base_price, 2) }}
                                @if ($stats)
                                    &middot; category avg ${{ number_format((float) $stats->avg_price, 2) }}
                                    (${{ number_format((float) $stats->min_price, 2) }}&ndash;${{ number_format((float) $stats->max_price, 2) }})
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ $product->status }}</span>
                    </div>

                    <div class="flex flex-wrap gap-3 mb-4">
                        <a href="{{ route('seller.dashboard.products.images', $product) }}" class="text-blue-600 hover:underline text-body-sm">Photos ({{ $product->images_count }})</a>
                        @if ($product->status === 'draft')
                            <form method="POST" action="{{ route('seller.dashboard.products.submit', $product) }}">
                                @csrf
                                <button type="submit" class="text-blue-600 hover:underline text-body-sm">Submit for review</button>
                            </form>
                        @endif
                        @if ($product->status !== 'archived')
                            <form method="POST" action="{{ route('seller.dashboard.products.archive', $product) }}" onsubmit="return confirm('Archive this product?')">
                                @csrf
                                <button type="submit" class="text-red-600 hover:underline text-body-sm">Archive</button>
                            </form>
                        @endif
                    </div>

                    {{-- TDD §5.6 / Design System §7.7: the "AI suggestion"
                         panel pattern — dashed border, explicit Accept/
                         Edit/Discard, never auto-applied. --}}
                    @if ($product->ai_suggested_description)
                        <div class="bg-blue-50 border border-dashed border-blue-200 rounded-sm p-4 mb-4">
                            <p class="text-caption uppercase tracking-wide text-blue-600 mb-2">AI-suggested description</p>
                            <p class="text-body-md text-slate-700 mb-3">{{ $product->ai_suggested_description }}</p>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('seller.dashboard.products.ai-description.accept', $product) }}">
                                    @csrf
                                    <button type="submit" class="rounded-sm bg-blue-600 text-slate-0 px-3 py-1.5 text-body-sm font-semibold">Accept</button>
                                </form>
                                <form method="POST" action="{{ route('seller.dashboard.products.ai-description.discard', $product) }}">
                                    @csrf
                                    <button type="submit" class="rounded-sm border border-slate-300 px-3 py-1.5 text-body-sm">Discard</button>
                                </form>
                            </div>
                            <p class="text-body-sm text-slate-500 mt-3">To edit before accepting: discard, adjust your notes, and regenerate.</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('seller.dashboard.products.ai-description.suggest', $product) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div class="flex-1 min-w-[240px]">
                                <label class="block text-body-sm text-slate-700 mb-1">Draft a description with AI &mdash; a few bullet points</label>
                                <input name="bullets" placeholder="e.g. waterproof, 10h battery, USB-C" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                            </div>
                            <button type="submit" class="h-10 rounded-sm border border-blue-600 text-blue-600 px-4 text-button font-semibold hover:bg-blue-50">Generate</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $products->links() }}</div>
    @endif
</x-layouts.dashboard>

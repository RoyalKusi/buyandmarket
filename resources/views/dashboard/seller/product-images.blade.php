<x-layouts.dashboard title="Product photos" active="seller.products">
    <a href="{{ route('seller.dashboard.products') }}" class="text-body-sm text-blue-600 hover:underline">&larr; Back to products</a>

    <h2 class="text-heading-sm font-display text-slate-900 mt-4 mb-1">{{ $product->title }}</h2>
    <p class="text-body-sm text-slate-500 mb-6">Photos run through quality checks on upload (minimum 500&times;500px, not too narrow/wide, under 10MB) — a rejected photo is kept below so you can see why.</p>

    <form method="POST" action="{{ route('seller.dashboard.products.images.store', $product) }}" enctype="multipart/form-data" class="bg-slate-0 border border-slate-100 rounded-md p-6 mb-6 flex items-end gap-3">
        @csrf
        <div class="flex-1">
            <label class="block text-body-sm text-slate-700 mb-1">Add a photo</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="w-full text-body-sm">
        </div>
        <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Upload</button>
    </form>

    @if ($product->images->isEmpty())
        <p class="text-body-md text-slate-500">No photos yet.</p>
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($product->images as $image)
                <div class="bg-slate-0 border border-slate-100 rounded-md overflow-hidden">
                    @if ($image->status === 'processed')
                        <img src="{{ $image->variantUrl('thumb') }}" alt="{{ $image->alt_text ?: $product->title }}" class="w-full aspect-square object-cover">
                    @else
                        <div class="w-full aspect-square bg-red-50 flex items-center justify-center p-3">
                            <p class="text-body-sm text-red-700 text-center">{{ $image->rejection_reason }}</p>
                        </div>
                    @endif

                    <div class="p-3 space-y-2">
                        @if ($image->status === 'processed')
                            @if ($image->is_primary)
                                <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-blue-50 text-blue-600">Primary</span>
                            @else
                                <form method="POST" action="{{ route('seller.dashboard.products.images.primary', [$product, $image]) }}">
                                    @csrf
                                    <button type="submit" class="text-body-sm text-blue-600 hover:underline">Make primary</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('seller.dashboard.products.images.alt-text', [$product, $image]) }}">
                                @csrf
                                <button type="submit" class="text-body-sm text-slate-600 hover:underline block">{{ $image->alt_text ? 'Regenerate alt text' : 'Generate alt text' }}</button>
                            </form>
                            @if ($image->alt_text)
                                <p class="text-body-sm text-slate-500 italic">&ldquo;{{ $image->alt_text }}&rdquo;</p>
                            @endif
                        @endif

                        <form method="POST" action="{{ route('seller.dashboard.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Remove this photo?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-body-sm text-red-600 hover:underline">Remove</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>

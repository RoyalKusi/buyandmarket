<div class="mt-6">
    @if ($product->variants->isNotEmpty())
        <div class="mb-4">
            <label for="variant" class="block text-body-md text-slate-700 mb-2">Options</label>
            <div class="flex flex-wrap gap-2">
                @foreach ($product->variants as $variant)
                    <button
                        type="button"
                        wire:click="$set('variantId', {{ $variant->id }})"
                        @disabled($variant->stock_quantity < 1)
                        class="h-10 px-4 rounded-full border text-body-sm flex items-center {{ $variantId === $variant->id ? 'border-blue-600 text-blue-600' : 'border-slate-200 text-slate-700' }} {{ $variant->stock_quantity < 1 ? 'opacity-50 cursor-not-allowed' : '' }}"
                    >
                        {{ $variant->sku }}
                        @if ($variant->stock_quantity < 1)
                            <span class="ml-2 text-red-600">(out of stock)</span>
                        @endif
                    </button>
                @endforeach
            </div>
            @error('variant') <p class="mt-1 text-body-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <label for="quantity" class="text-body-md text-slate-700">Qty</label>
        <input id="quantity" type="number" min="1" wire:model="quantity" class="w-20 h-10 rounded-sm border border-slate-200 px-3 text-body-md">
    </div>

    <div class="flex flex-col gap-2">
        <button type="button" wire:click="addToCart" class="h-12 rounded-sm border border-blue-600 text-blue-600 text-button font-semibold hover:bg-blue-50">
            Add to cart
        </button>
        <button type="button" wire:click="buyNow" class="h-12 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">
            Buy now
        </button>
    </div>

    @if ($status)
        <p class="mt-2 text-body-sm text-green-700">{{ $status }}</p>
    @endif
</div>

<x-layouts.dashboard title="Wishlist" active="wishlist">
    @if ($items->isEmpty())
        <p class="text-body-lg text-slate-500">Nothing saved yet — tap the heart on a product page to add it here.</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($items as $item)
                @php($product = $item->product)
                <div class="bg-slate-0 border border-slate-100 rounded-md overflow-hidden">
                    <a href="{{ route('storefront.products.show', $product) }}" class="block aspect-square bg-slate-25 flex items-center justify-center">
                        @if ($product->primaryImage())
                            <img src="{{ $product->primaryImage()->variantUrl('thumb') }}" alt="{{ $product->title }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-display-xl text-slate-300 font-display">{{ Str::of($product->title)->substr(0, 1)->upper() }}</span>
                        @endif
                    </a>
                    <div class="p-4">
                        <a href="{{ route('storefront.products.show', $product) }}" class="text-body-md font-semibold text-slate-900 hover:text-blue-600">{{ $product->title }}</a>
                        <p class="text-body-sm text-slate-500 mt-1">${{ number_format((float) $product->base_price, 2) }}</p>
                        <form method="POST" action="{{ route('dashboard.wishlist.destroy', $product) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-body-sm text-red-600 hover:underline">Remove</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</x-layouts.dashboard>

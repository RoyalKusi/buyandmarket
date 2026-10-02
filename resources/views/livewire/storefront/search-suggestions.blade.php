<div class="relative" x-data @click.outside="$wire.close()">
    <label for="storefront-search" class="sr-only">Search products</label>
    <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-4.3-4.3m1.8-5.2a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
            id="storefront-search"
            type="search"
            name="q"
            autocomplete="off"
            wire:model.live.debounce.250ms="query"
            placeholder="Search products, stores..."
            class="w-full h-11 rounded-full bg-slate-50 pl-11 pr-4 text-body-lg text-slate-900 placeholder:text-slate-400 border border-transparent focus:bg-slate-0 focus:border-blue-600 focus:outline-none transition-colors duration-fast"
        >
    </div>

    @if ($open)
        <div class="absolute z-50 mt-2 w-full bg-slate-0 border border-slate-100 rounded-md shadow-2 overflow-hidden">
            @forelse ($suggestions as $product)
                <a href="{{ route('storefront.products.show', $product) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                    <span class="w-10 h-10 shrink-0 rounded-sm bg-slate-25 flex items-center justify-center text-body-sm text-slate-300 font-display" aria-hidden="true">
                        {{ Str::of($product->title)->substr(0, 1)->upper() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block text-body-md text-slate-900 truncate">{{ $product->title }}</span>
                        <span class="block text-body-sm text-slate-500 truncate">{{ $product->store->name }}</span>
                    </span>
                </a>
            @empty
                <p class="px-4 py-3 text-body-sm text-slate-500">No matches yet — press enter to search anyway.</p>
            @endforelse
        </div>
    @endif
</div>

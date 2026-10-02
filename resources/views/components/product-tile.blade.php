{{--
    Design System §4.3: the single most repeated component, specified to
    the pixel. Omitted here (all flagged in CHANGELOG.md, Run 1.4): the
    wishlist heart (module 34 not built), star rating row (module 33 not
    built), discount chip (module 14 not built), delivery line (module 26
    not wired into the storefront yet) — each needs its own module before
    it can render real data rather than a fake placeholder.
--}}
@props(['product'])

<article class="bg-slate-0 border border-slate-100 rounded-md shadow-1 hover:shadow-2 transition-shadow duration-base overflow-hidden flex flex-col">
    <a href="{{ route('storefront.products.show', $product) }}" class="block">
        <div class="aspect-square bg-slate-25 rounded-t-md flex items-center justify-center" aria-hidden="true">
            <span class="text-display-md text-slate-300 font-display">{{ Str::of($product->title)->substr(0, 1)->upper() }}</span>
        </div>
    </a>

    <div class="px-5 pt-4 pb-4 flex flex-col flex-1">
        <a href="{{ route('storefront.products.show', $product) }}" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-xs">
            <h3 class="text-heading-sm text-slate-900 line-clamp-2 min-h-[2.75rem]">{{ $product->title }}</h3>
        </a>

        <p class="text-body-sm text-slate-500 mt-1 truncate">
            {{ $product->store->name }}
        </p>

        <p class="text-price-md text-blue-600 mt-2">
            ${{ number_format((float) ($product->variants->first()?->price_override ?? $product->base_price), 2) }}
        </p>

        <a
            href="{{ route('storefront.products.show', $product) }}"
            class="mt-4 inline-flex items-center justify-center w-full h-10 rounded-sm border border-blue-600 text-blue-600 text-button hover:bg-blue-50 transition-colors duration-fast"
        >
            View product
        </a>
    </div>
</article>

{{--
    Design System §4.3 store card. Omitted (flagged in CHANGELOG.md, Run
    1.4): category tag chips and star rating — both need modules not yet
    built (product-category tagging surfaced at the store level, and
    reviews). Deliberately landscape/avatar so it never reads as a product
    tile.
--}}
@props(['store'])

<div class="bg-slate-0 border border-slate-100 rounded-md shadow-1 p-4 flex items-center gap-4">
    <div class="w-24 h-24 rounded-full bg-slate-100 flex items-center justify-center shrink-0" aria-hidden="true">
        <span class="text-heading-lg text-slate-400 font-display">{{ Str::of($store->name)->substr(0, 1)->upper() }}</span>
    </div>

    <div class="flex-1 min-w-0">
        <h3 class="text-heading-sm text-slate-900 truncate">{{ $store->name }}</h3>
        <p class="text-body-sm text-slate-500 mt-1">{{ $store->products()->published()->count() }} products</p>

        <a
            href="{{ route('storefront.stores.show', $store) }}"
            class="mt-3 inline-flex items-center justify-center h-8 px-4 rounded-sm text-blue-600 text-button hover:bg-blue-50 transition-colors duration-fast"
        >
            Visit store
        </a>
    </div>
</div>

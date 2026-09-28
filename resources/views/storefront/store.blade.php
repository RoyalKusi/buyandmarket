<x-layouts.storefront :title="$store->name">
    {{--
        Design System §6.5. Banner image, avatar overlap, follow/message
        buttons and the Categories/About/Reviews/Policies tabs beyond "All
        Products" are deferred — flagged in CHANGELOG.md, Run 1.4.
    --}}
    <div class="border-b border-slate-100 bg-slate-0">
        <div class="mx-auto max-w-[1280px] px-4 md:px-6 py-8 flex items-center gap-4">
            <div class="w-24 h-24 rounded-full bg-slate-100 flex items-center justify-center shrink-0" aria-hidden="true">
                <span class="text-heading-lg text-slate-400 font-display">{{ Str::of($store->name)->substr(0, 1)->upper() }}</span>
            </div>
            <div>
                <h1 class="text-display-md text-slate-900">{{ $store->name }}</h1>
                <p class="text-body-md text-slate-500 mt-1">{{ $store->products()->published()->count() }} products</p>
            </div>
        </div>
    </div>

    <nav aria-label="Store sections" class="border-b border-slate-100 bg-slate-0">
        <div class="mx-auto max-w-[1280px] px-4 md:px-6 flex gap-6 text-body-md">
            <span class="py-3 border-b-2 border-blue-600 text-blue-600 font-medium">All Products</span>
            <span class="py-3 text-slate-400" aria-disabled="true">Categories</span>
            <span class="py-3 text-slate-400" aria-disabled="true">About</span>
            <span class="py-3 text-slate-400" aria-disabled="true">Reviews</span>
            <span class="py-3 text-slate-400" aria-disabled="true">Policies</span>
        </div>
    </nav>

    <div class="mx-auto max-w-[1280px] px-4 md:px-6 py-8">
        @livewire('product-grid', ['storeId' => $store->id])
    </div>
</x-layouts.storefront>

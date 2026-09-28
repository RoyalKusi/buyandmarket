@php($pageTitle = $query ? "Search: {$query}" : 'Search')
<x-layouts.storefront :title="$pageTitle">
    <div class="mx-auto max-w-[1280px] px-4 md:px-6 py-8">
        <h1 class="text-heading-lg text-slate-900 mb-6">
            @if ($query)
                Results for &ldquo;{{ $query }}&rdquo;
            @else
                All products
            @endif
        </h1>

        @livewire('product-grid', ['q' => $query ?: null])
    </div>
</x-layouts.storefront>

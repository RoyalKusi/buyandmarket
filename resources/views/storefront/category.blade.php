<x-layouts.storefront :title="$category->name">
    <div class="mx-auto max-w-[1280px] px-4 md:px-6 py-8">
        <x-breadcrumbs :items="$breadcrumbs" />

        <div class="mt-4 mb-6">
            <h1 class="text-display-md text-slate-900">{{ $category->name }}</h1>
        </div>

        @if ($children->isNotEmpty())
            <div class="flex gap-2 overflow-x-auto pb-2 mb-6">
                @foreach ($children as $child)
                    <a
                        href="{{ route('storefront.categories.show', $child) }}"
                        class="shrink-0 h-8 px-4 rounded-full border border-slate-200 text-body-sm text-slate-700 flex items-center hover:border-blue-600 hover:text-blue-600 transition-colors duration-fast"
                    >
                        {{ $child->name }}
                    </a>
                @endforeach
            </div>
        @endif

        @livewire('product-grid', ['categoryId' => $category->id])
    </div>
</x-layouts.storefront>

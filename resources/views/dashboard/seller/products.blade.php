<x-layouts.dashboard title="Products" active="seller.products">
    {{-- A web product-creation form (category picker, dynamic variant
         rows) is deferred — see CHANGELOG.md. Product creation is
         fully functional via the tested API
         (POST /api/v1/seller/products); this page manages the
         lifecycle of products that already exist. --}}
    @if ($products->isEmpty())
        <p class="text-body-lg text-slate-500">No products yet. Create one via the seller API.</p>
    @else
        <div class="bg-slate-0 border border-slate-100 rounded-md overflow-hidden">
            <table class="w-full text-body-md">
                <thead class="bg-slate-50 text-body-sm text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Title</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-right px-4 py-3 font-medium">Price</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ $product->title }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ $product->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">${{ number_format((float) $product->base_price, 2) }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                @if ($product->status === 'draft')
                                    <form method="POST" action="{{ route('seller.dashboard.products.submit', $product) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-blue-600 hover:underline">Submit for review</button>
                                    </form>
                                @endif
                                @if ($product->status !== 'archived')
                                    <form method="POST" action="{{ route('seller.dashboard.products.archive', $product) }}" class="inline" onsubmit="return confirm('Archive this product?')">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:underline">Archive</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $products->links() }}</div>
    @endif
</x-layouts.dashboard>

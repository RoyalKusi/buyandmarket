<x-layouts.dashboard title="Product moderation" active="admin.products">
    @if ($products->isEmpty())
        <p class="text-body-lg text-slate-500">No products waiting for review.</p>
    @else
        <div class="space-y-4">
            @foreach ($products as $product)
                <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-heading-sm font-display text-slate-900">{{ $product->title }}</h2>
                            <p class="text-body-sm text-slate-500">{{ $product->store->seller->business_name }} &middot; ${{ number_format((float) $product->base_price, 2) }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.dashboard.products.approve', $product) }}">
                            @csrf
                            <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Approve</button>
                        </form>

                        <form method="POST" action="{{ route('admin.dashboard.products.reject', $product) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div>
                                <label class="block text-body-sm text-slate-700 mb-1">Reason code</label>
                                <input name="reason_code" required class="h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                            </div>
                            <div>
                                <label class="block text-body-sm text-slate-700 mb-1">Note</label>
                                <input name="note" class="h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                            </div>
                            <button type="submit" class="h-10 rounded-sm border border-red-600 text-red-600 px-4 text-button font-semibold hover:bg-red-50">Reject</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>

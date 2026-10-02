<x-layouts.dashboard title="Sponsored campaigns" active="seller.sponsored">
    {{-- TDD module 15 — deferred since Run 1.4, added in Run 1.20.
         daily_budget is declarative only: no billing is wired to it yet
         (see CHANGELOG.md). --}}
    <div class="bg-slate-0 border border-slate-100 rounded-md p-6 mb-6 max-w-xl">
        <h2 class="text-heading-sm font-display text-slate-900 mb-4">New campaign</h2>

        @if ($products->isEmpty())
            <p class="text-body-md text-slate-500">You need a published product before you can sponsor one.</p>
        @else
            <form method="POST" action="{{ route('seller.dashboard.sponsored-campaigns.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-body-sm text-slate-700 mb-1">Product</label>
                    <select name="product_id" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Daily budget (USD)</label>
                        <input type="number" step="0.01" min="1" name="daily_budget" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Starts</label>
                        <input type="date" name="starts_at" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Ends (optional)</label>
                        <input type="date" name="ends_at" class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>
                </div>
                <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Submit for approval</button>
            </form>
        @endif
    </div>

    @if ($campaigns->isNotEmpty())
        <div class="space-y-3">
            @foreach ($campaigns as $campaign)
                <div class="bg-slate-0 border border-slate-100 rounded-md p-4 flex items-center justify-between">
                    <div>
                        <p class="text-body-md font-semibold text-slate-900">{{ $campaign->product->title }}</p>
                        <p class="text-body-sm text-slate-500">${{ number_format((float) $campaign->daily_budget, 2) }}/day &middot; {{ $campaign->starts_at->format('d M Y') }}{{ $campaign->ends_at ? ' – '.$campaign->ends_at->format('d M Y') : '' }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ $campaign->status }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>

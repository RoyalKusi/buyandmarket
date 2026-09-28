<x-layouts.dashboard title="Seller overview" active="seller.overview">
    {{-- Design System §6.11: KPI stat cards. Views/conversion sparklines
         need the analytics event stream (TDD §3.6 module 41, not yet
         built) — these three cards use figures this run's own schema can
         compute honestly, rather than fabricating the rest. --}}
    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
            <p class="text-body-sm text-slate-500 mb-1">Completed revenue</p>
            <p class="text-heading-lg font-display text-slate-900 tabular-nums">${{ number_format($revenue, 2) }}</p>
        </div>
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
            <p class="text-body-sm text-slate-500 mb-1">Commission owed</p>
            <p class="text-heading-lg font-display text-slate-900 tabular-nums">${{ number_format($pendingCommission, 2) }}</p>
        </div>
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
            <p class="text-body-sm text-slate-500 mb-1">Store status</p>
            <p class="text-heading-lg font-display text-slate-900 capitalize">{{ $seller->status }}</p>
        </div>
    </div>

    <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
        <h2 class="text-heading-sm font-display text-slate-900 mb-4">Orders by status</h2>
        <div class="flex flex-wrap gap-3">
            @forelse ($statusCounts as $status => $count)
                <span class="inline-flex items-center gap-2 rounded-sm bg-slate-50 px-3 py-2 text-body-sm text-slate-700 capitalize">
                    {{ $status }} <span class="font-semibold tabular-nums">{{ $count }}</span>
                </span>
            @empty
                <p class="text-body-md text-slate-500">No orders yet.</p>
            @endforelse
        </div>
    </div>
</x-layouts.dashboard>

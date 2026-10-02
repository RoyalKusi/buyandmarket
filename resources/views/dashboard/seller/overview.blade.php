<x-layouts.dashboard title="Seller overview" active="seller.overview">
    {{-- Design System §6.11: KPI stat cards. --}}
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

    <div class="bg-slate-0 border border-slate-100 rounded-md p-6 mb-6">
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

    {{-- TDD §5.6 "inventory alerts" (see App\Http\Controllers\Dashboard\
         SellerController::overview for why this is a stock threshold,
         not a sales-velocity reorder point). --}}
    <div class="bg-slate-0 border border-slate-100 rounded-md p-6 mb-6">
        <h2 class="text-heading-sm font-display text-slate-900 mb-4">Low stock</h2>
        @if ($lowStockVariants->isEmpty())
            <p class="text-body-md text-slate-500">Nothing running low.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($lowStockVariants as $variant)
                    <li class="py-2 flex items-center justify-between text-body-md">
                        <span>{{ $variant->product->title }} ({{ $variant->sku }})</span>
                        <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-amber-50 text-amber-700 tabular-nums">{{ $variant->stock_quantity }} left</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- TDD module 41 / §5.6 "sales summaries, performance insights" —
         deferred since Run 1.7/1.11 for lacking the analytics event
         stream; wired in Run 1.22 (App\Services\SellerAnalyticsService),
         computed purely from real recorded events, nothing estimated. --}}
    <div class="bg-slate-0 border border-slate-100 rounded-md p-6 mb-6">
        <h2 class="text-heading-sm font-display text-slate-900 mb-1">Sales, last 30 days</h2>
        <p class="text-heading-lg font-display text-slate-900 tabular-nums mb-4">${{ number_format($salesSummary['totalRevenue'], 2) }}</p>

        @if ($salesSummary['totalRevenue'] > 0)
            <div class="flex items-end gap-0.5 h-20">
                @php($peak = max($salesSummary['revenue']) ?: 1)
                @foreach ($salesSummary['revenue'] as $day)
                    <div class="flex-1 bg-blue-100 rounded-t-xs" style="height: {{ max(2, ($day / $peak) * 100) }}%" title="${{ number_format($day, 2) }}"></div>
                @endforeach
            </div>
        @else
            <p class="text-body-md text-slate-500">No confirmed sales in the last 30 days yet.</p>
        @endif
    </div>

    <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
        <h2 class="text-heading-sm font-display text-slate-900 mb-4">Product performance</h2>
        @if ($productPerformance->isEmpty())
            <p class="text-body-md text-slate-500">No products yet.</p>
        @else
            <table class="w-full text-body-sm">
                <thead class="text-slate-500">
                    <tr>
                        <th class="text-left font-medium pb-2">Product</th>
                        <th class="text-right font-medium pb-2">Views</th>
                        <th class="text-right font-medium pb-2">Purchases</th>
                        <th class="text-right font-medium pb-2">Conversion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($productPerformance as $row)
                        <tr class="border-t border-slate-100">
                            <td class="py-2">{{ $row['product']->title }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $row['views'] }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $row['purchases'] }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $row['conversionRate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.dashboard>

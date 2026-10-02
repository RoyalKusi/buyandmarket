<x-layouts.dashboard title="Delivery" active="seller.delivery">
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="bg-slate-0 border border-slate-100 rounded-md overflow-hidden">
            <table class="w-full text-body-md">
                <thead class="bg-slate-50 text-body-sm text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Zone</th>
                        <th class="text-left px-4 py-3 font-medium">Method</th>
                        <th class="text-right px-4 py-3 font-medium">Fee</th>
                        <th class="text-left px-4 py-3 font-medium">ETA</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rateCards as $rateCard)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ $rateCard->zone->name }}</td>
                            <td class="px-4 py-3 capitalize">{{ $rateCard->method }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">${{ number_format((float) $rateCard->base_fee, 2) }}</td>
                            <td class="px-4 py-3">{{ $rateCard->eta_min_days }}-{{ $rateCard->eta_max_days }} days</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-body-md text-slate-500">No delivery zones configured yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-slate-0 border border-slate-100 rounded-md p-6 h-fit">
            {{-- TDD §3.4 module 25: a seller "opts into" a zone by
                 creating a rate card for it — there's no separate list. --}}
            <h2 class="text-heading-sm font-display text-slate-900 mb-4">Add a delivery zone</h2>
            <form method="POST" action="{{ route('seller.dashboard.delivery.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-body-sm text-slate-700 mb-1">Zone</label>
                    <select name="zone_id" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }} ({{ $zone->level }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-body-sm text-slate-700 mb-1">Method</label>
                    <select name="method" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        <option value="standard">Standard</option>
                        <option value="express">Express</option>
                        <option value="pickup">Pickup</option>
                    </select>
                </div>
                <div>
                    <label class="block text-body-sm text-slate-700 mb-1">Base fee ($)</label>
                    <input type="number" step="0.01" min="0" name="base_fee" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                </div>
                <div>
                    <label class="block text-body-sm text-slate-700 mb-1">Free delivery above ($, optional)</label>
                    <input type="number" step="0.01" min="0" name="free_threshold" class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">ETA min (days)</label>
                        <input type="number" min="0" name="eta_min_days" value="2" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">ETA max (days)</label>
                        <input type="number" min="0" name="eta_max_days" value="4" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>
                </div>
                <button type="submit" class="w-full h-10 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">Save</button>
            </form>
        </div>
    </div>
</x-layouts.dashboard>

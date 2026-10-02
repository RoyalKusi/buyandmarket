<x-layouts.dashboard title="Sponsored campaigns" active="admin.sponsored">
    @if ($campaigns->isEmpty())
        <p class="text-body-lg text-slate-500">No campaigns awaiting review.</p>
    @else
        <div class="space-y-4">
            @foreach ($campaigns as $campaign)
                <div class="bg-slate-0 border border-slate-100 rounded-md p-6 flex items-center justify-between">
                    <div>
                        <p class="text-body-md font-semibold text-slate-900">{{ $campaign->product->title }}</p>
                        <p class="text-body-sm text-slate-500">{{ $campaign->seller->business_name }} &middot; ${{ number_format((float) $campaign->daily_budget, 2) }}/day &middot; {{ $campaign->starts_at->format('d M Y') }}{{ $campaign->ends_at ? ' – '.$campaign->ends_at->format('d M Y') : '' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.dashboard.sponsored-campaigns.approve', $campaign) }}">
                            @csrf
                            <button type="submit" class="rounded-sm bg-blue-600 text-slate-0 px-3 py-1.5 text-body-sm font-semibold">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.dashboard.sponsored-campaigns.reject', $campaign) }}">
                            @csrf
                            <button type="submit" class="rounded-sm border border-slate-300 px-3 py-1.5 text-body-sm">Reject</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>

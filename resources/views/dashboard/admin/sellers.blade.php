<x-layouts.dashboard title="Seller approvals" active="admin.sellers">
    @if ($sellers->isEmpty())
        <p class="text-body-lg text-slate-500">No sellers waiting for review.</p>
    @else
        <div class="space-y-4">
            @foreach ($sellers as $seller)
                <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-heading-sm font-display text-slate-900">{{ $seller->business_name }}</h2>
                            <p class="text-body-sm text-slate-500">{{ $seller->user->name }} &middot; {{ $seller->user->email }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700">{{ $seller->kycDocuments->count() }} document(s)</span>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.dashboard.sellers.approve', $seller) }}">
                            @csrf
                            <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Approve</button>
                        </form>

                        {{-- TDD §4.2: rejection always carries a mandatory
                             reason code + free-text note. --}}
                        <form method="POST" action="{{ route('admin.dashboard.sellers.reject', $seller) }}" class="flex flex-wrap items-end gap-2">
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

<x-layouts.dashboard title="AI system monitoring" active="admin.ai">
    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
            <p class="text-body-sm text-slate-500 mb-1">Conversations</p>
            <p class="text-heading-lg font-display text-slate-900 tabular-nums">{{ $conversationCount }}</p>
        </div>
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
            <p class="text-body-sm text-slate-500 mb-1">Buyer messages</p>
            <p class="text-heading-lg font-display text-slate-900 tabular-nums">{{ $messageCounts['user'] ?? 0 }}</p>
        </div>
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
            <p class="text-body-sm text-slate-500 mb-1">Pending confirmations</p>
            <p class="text-heading-lg font-display text-slate-900 tabular-nums">{{ $pendingConfirmations }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
            <h2 class="text-heading-sm font-display text-slate-900 mb-4">Tool calls</h2>
            @if ($toolCallCounts->isEmpty())
                <p class="text-body-md text-slate-500">No tool calls yet.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach ($toolCallCounts as $tool => $count)
                        <span class="inline-flex items-center gap-2 rounded-sm bg-slate-50 px-3 py-2 text-body-sm text-slate-700">
                            {{ $tool }} <span class="font-semibold tabular-nums">{{ $count }}</span>
                        </span>
                    @endforeach
                </div>
            @endif

            <p class="text-body-sm text-slate-500 mt-4">{{ $confirmedActions }} state-changing action(s) confirmed and executed.</p>
        </div>

        <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
            <h2 class="text-heading-sm font-display text-slate-900 mb-4">Seller AI listing assistant</h2>
            <ul class="space-y-2 text-body-md text-slate-700">
                <li>Suggestions generated: <span class="font-semibold tabular-nums">{{ $suggestionsGenerated }}</span></li>
                <li>Accepted: <span class="font-semibold tabular-nums">{{ $suggestionsAccepted }}</span></li>
                <li>Discarded: <span class="font-semibold tabular-nums">{{ $suggestionsDiscarded }}</span></li>
            </ul>
        </div>
    </div>
</x-layouts.dashboard>

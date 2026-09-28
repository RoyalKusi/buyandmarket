<div class="bg-slate-900 rounded-md p-4 flex flex-col h-[70vh]">
    <div class="flex-1 overflow-y-auto space-y-3 pr-1">
        @forelse ($messages as $message)
            @if ($message->role === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[80%] bg-blue-600 text-slate-0 rounded-md rounded-br-none px-4 py-2 text-body-lg">
                        {{ $message->content }}
                    </div>
                </div>
            @elseif ($message->role === 'assistant')
                <div class="flex justify-start">
                    <div class="max-w-[80%] bg-slate-800 text-slate-0 rounded-md rounded-bl-none px-4 py-2 text-body-lg">
                        @if ($message->content)
                            <p>{{ $message->content }}</p>
                        @endif

                        @if (! empty($message->citations))
                            <p class="mt-2 text-caption text-slate-400">
                                From:
                                @foreach ($message->citations as $citation)
                                    <span class="underline decoration-dotted">{{ $citation['type'] }}#{{ $citation['id'] }}</span>{{ ! $loop->last ? ',' : '' }}
                                @endforeach
                            </p>
                        @endif

                        {{-- Design System §7.3: action confirmation card —
                             a distinct, explicit Confirm/Cancel pair; the
                             assistant never executes a state-changing tool
                             without this step (TDD §5.4). --}}
                        @if ($message->requires_confirmation && ! $message->confirmed)
                            <div class="mt-3 border border-cyan-500 rounded-sm p-3">
                                <p class="text-body-sm mb-2">
                                    Confirm: {{ $message->tool_calls[0]['name'] ?? 'this action' }}
                                    ({{ json_encode($message->tool_calls[0]['arguments'] ?? []) }})
                                </p>
                                <div class="flex gap-2">
                                    <button wire:click="confirm({{ $message->id }})" class="rounded-sm bg-cyan-500 text-slate-900 px-3 py-1.5 text-body-sm font-semibold">Confirm</button>
                                    <button wire:click="cancel({{ $message->id }})" class="rounded-sm border border-slate-600 px-3 py-1.5 text-body-sm">Cancel</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <p class="text-body-md text-slate-400">Ask about products, orders, or delivery&hellip;</p>
        @endforelse
    </div>

    <form wire:submit="send" class="mt-4 flex gap-2">
        <label for="ai-draft" class="sr-only">Message</label>
        <input
            id="ai-draft"
            type="text"
            wire:model="draft"
            placeholder="Ask about products, orders, delivery…"
            class="flex-1 h-12 rounded-sm bg-slate-800 text-slate-0 placeholder:text-slate-500 px-3 text-body-lg border border-transparent focus:border-cyan-500 focus:outline-none"
        >
        <button type="submit" class="h-12 w-12 rounded-full bg-cyan-500 text-slate-900 flex items-center justify-center" aria-label="Send">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 19V5m0 0l-6 6m6-6l6 6" />
            </svg>
        </button>
    </form>
</div>

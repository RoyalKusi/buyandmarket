<div>
    <button type="button" wire:click="toggle" class="relative h-11 w-11 flex items-center justify-center rounded-full hover:bg-slate-50" aria-label="Cart">
        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 3h2l.4 2M7 13h10l3-8H5.4M7 13L5.4 5M7 13l-2 5h13M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" />
        </svg>
        @if ($itemCount > 0)
            <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 rounded-full bg-blue-600 text-slate-0 text-caption flex items-center justify-center tabular-nums">{{ $itemCount }}</span>
        @endif
    </button>

    @if ($open)
        {{-- Design System §4.7: right-side drawer (desktop), 400px, settle
             entrance. Simplified to a fixed-position panel without the
             motion-timing/bottom-sheet-on-mobile variant (flagged in
             CHANGELOG.md). --}}
        <div class="fixed inset-0 z-50 flex justify-end" role="dialog" aria-label="Shopping cart">
            <div class="absolute inset-0 bg-slate-950/50" wire:click="toggle"></div>

            <div class="relative w-full max-w-[400px] h-full bg-slate-0 shadow-xl flex flex-col">
                <div class="h-[72px] px-6 flex items-center justify-between border-b border-slate-100">
                    <h2 class="text-heading-sm font-display text-slate-900">Your cart</h2>
                    <button type="button" wire:click="toggle" class="h-11 w-11 flex items-center justify-center rounded-full hover:bg-slate-50" aria-label="Close cart">&times;</button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4">
                    @forelse ($groups as $storeId => $items)
                        <div class="mb-6">
                            <p class="text-body-sm font-medium text-slate-700 mb-2">{{ $items->first()->variant->product->store->name }}</p>
                            <ul class="space-y-3">
                                @foreach ($items as $item)
                                    <li class="flex gap-3">
                                        <div class="w-16 h-16 bg-slate-50 rounded-sm flex items-center justify-center text-slate-300 text-heading-sm font-display shrink-0">
                                            {{ Str::of($item->variant->product->title)->substr(0, 1)->upper() }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-body-md text-slate-900 line-clamp-2">{{ $item->variant->product->title }}</p>
                                            <p class="text-body-sm text-slate-500">${{ number_format((float) $item->price_snapshot, 2) }}</p>
                                            <div class="mt-1 flex items-center gap-2">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value="{{ $item->quantity }}"
                                                    wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                                    class="w-16 h-8 rounded-sm border border-slate-200 px-2 text-body-sm"
                                                >
                                                <button type="button" wire:click="removeItem({{ $item->id }})" class="text-body-sm text-red-600 hover:underline">Remove</button>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-body-md text-slate-500">Your cart is empty.</p>
                    @endforelse
                </div>

                @if ($itemCount > 0)
                    <div class="border-t border-slate-100 p-6">
                        <div class="flex justify-between text-body-lg text-slate-900 mb-4">
                            <span>Subtotal</span>
                            <span class="tabular-nums">${{ number_format((float) $subtotal, 2) }}</span>
                        </div>
                        <a href="{{ route('storefront.checkout.start') }}" class="block text-center h-12 leading-[48px] rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">
                            Proceed to checkout
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

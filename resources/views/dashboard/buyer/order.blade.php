<x-layouts.dashboard :title="'Order '.$order->order_number" active="buyer">
    <a href="{{ route('dashboard') }}" class="text-body-sm text-slate-500 hover:text-slate-700">&larr; Back to orders</a>

    <div class="mt-4 space-y-6">
        @foreach ($order->orderGroups as $orderGroup)
            <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-heading-sm font-display text-slate-900">{{ $orderGroup->seller->business_name }}</h2>
                    <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ $orderGroup->status }}</span>
                </div>

                <ul class="divide-y divide-slate-100 mb-4">
                    @foreach ($orderGroup->items as $item)
                        <li class="py-2 flex items-center justify-between text-body-md">
                            <span>{{ $item->variant->product->title }} &times; {{ $item->quantity }}</span>
                            <span class="tabular-nums">${{ number_format((float) $item->price_at_purchase * $item->quantity, 2) }}</span>
                        </li>
                    @endforeach
                </ul>

                @if ($orderGroup->shipment)
                    {{-- Design System §6.4/§6.11: the tracking timeline is a
                         read projection of the append-only shipment_events
                         log (TDD §3.4 module 24). --}}
                    <div class="border-t border-slate-100 pt-4">
                        <h3 class="text-body-md font-medium text-slate-900 mb-2">Delivery status: <span class="capitalize">{{ $orderGroup->shipment->status }}</span></h3>
                        <ol class="space-y-2">
                            @foreach ($orderGroup->shipment->events as $event)
                                <li class="text-body-sm text-slate-600 flex gap-3">
                                    <span class="tabular-nums text-slate-400">{{ $event->created_at->format('d M, H:i') }}</span>
                                    <span class="capitalize">{{ str_replace('_', ' ', $event->event_type) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.dashboard>

<x-layouts.dashboard title="My deliveries" active="shipper">
    <section class="mb-8">
        <h2 class="text-heading-sm font-display text-slate-900 mb-4">My deliveries</h2>
        @if ($myShipments->isEmpty())
            <p class="text-body-md text-slate-500">Nothing assigned to you right now.</p>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($myShipments as $shipment)
                    <div class="bg-slate-0 border border-slate-100 rounded-md p-5">
                        <div class="flex items-center justify-between mb-2">
                            <p class="font-mono text-body-sm text-slate-500">{{ $shipment->orderGroup->order->order_number }}</p>
                            <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ $shipment->status }}</span>
                        </div>
                        <p class="text-body-sm text-slate-600 mb-1">Deliver to: {{ $shipment->zone->name }}</p>
                        <p class="text-body-sm text-slate-600 mb-4 capitalize">Method: {{ $shipment->method }}</p>

                        {{-- TDD §4.3 / Design System §6.11: status stepper
                             (Assigned -> Picked up -> In transit ->
                             Delivered), one-tap next-status action; proof
                             of delivery (photo + signature) required on
                             the final step. --}}
                        <form method="POST" action="{{ route('shipper.dashboard.events.store', $shipment) }}" enctype="multipart/form-data" class="space-y-2">
                            @csrf
                            @php($next = match ($shipment->status) {
                                'assigned' => 'picked_up',
                                'picked_up' => 'in_transit',
                                'in_transit' => 'out_for_delivery',
                                'out_for_delivery' => 'delivered',
                                default => null,
                            })
                            @if ($next)
                                <input type="hidden" name="event_type" value="{{ $next }}">
                                @if ($next === 'delivered')
                                    <label class="block text-body-sm text-slate-700">Proof photo
                                        <input type="file" name="photo" accept="image/*" required class="mt-1 block w-full text-body-sm">
                                    </label>
                                    <label class="block text-body-sm text-slate-700">Signature
                                        <input type="file" name="signature" accept="image/*" required class="mt-1 block w-full text-body-sm">
                                    </label>
                                @endif
                                <button type="submit" class="w-full h-10 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500 capitalize">
                                    Mark {{ str_replace('_', ' ', $next) }}
                                </button>
                            @endif
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <h2 class="text-heading-sm font-display text-slate-900 mb-4">Available deliveries</h2>
        @if ($unclaimedShipments->isEmpty())
            <p class="text-body-md text-slate-500">No unclaimed deliveries right now.</p>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($unclaimedShipments as $shipment)
                    <div class="bg-slate-0 border border-slate-100 rounded-md p-5">
                        <p class="font-mono text-body-sm text-slate-500 mb-1">{{ $shipment->orderGroup->order->order_number }}</p>
                        <p class="text-body-sm text-slate-600 mb-4">Deliver to: {{ $shipment->zone->name }} &middot; <span class="capitalize">{{ $shipment->method }}</span></p>
                        <form method="POST" action="{{ route('shipper.dashboard.claim', $shipment) }}">
                            @csrf
                            <button type="submit" class="w-full h-10 rounded-sm border border-blue-600 text-blue-600 text-button font-semibold hover:bg-blue-50">Claim</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.dashboard>

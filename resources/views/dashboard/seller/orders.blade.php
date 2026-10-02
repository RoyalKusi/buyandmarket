<x-layouts.dashboard title="Orders" active="seller.orders">
    @if ($orderGroups->isEmpty())
        <p class="text-body-lg text-slate-500">No orders yet.</p>
    @else
        <div class="space-y-4">
            @foreach ($orderGroups as $orderGroup)
                <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                    <div class="flex items-center justify-between mb-3">
                        <p class="font-mono text-body-sm text-slate-500">{{ $orderGroup->order->order_number }}</p>
                        <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ $orderGroup->status }}</span>
                    </div>

                    <ul class="text-body-sm text-slate-600 mb-4">
                        @foreach ($orderGroup->items as $item)
                            <li>{{ $item->variant->product->title }} &times; {{ $item->quantity }}</li>
                        @endforeach
                    </ul>

                    @if ($orderGroup->shipment)
                        <p class="text-body-sm text-slate-700">Shipment: <span class="capitalize font-medium">{{ $orderGroup->shipment->status }}</span></p>
                    @elseif (in_array($orderGroup->status, ['confirmed', 'processing']))
                        {{-- TDD §3.4 module 22: assigning a shipment without
                             a shipper dispatches it to the pooled shipper
                             marketplace (App\Services\ShippingService::claim()). --}}
                        <form method="POST" action="{{ route('seller.dashboard.orders.shipment', $orderGroup) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label class="block text-body-sm text-slate-700 mb-1">Delivery zone</label>
                                <select name="zone_id" required class="h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-body-sm text-slate-700 mb-1">Method</label>
                                <select name="method" required class="h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                                    <option value="standard">Standard</option>
                                    <option value="express">Express</option>
                                    <option value="pickup">Pickup</option>
                                </select>
                            </div>
                            <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Assign shipment</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $orderGroups->links() }}</div>
    @endif
</x-layouts.dashboard>

<x-layouts.dashboard title="My orders" active="buyer">
    @if ($orders->isEmpty())
        <div class="text-center py-16">
            <x-icon name="orders" class="w-12 h-12 mx-auto text-slate-300" />
            <p class="mt-3 text-body-lg text-slate-500">You haven't placed any orders yet.</p>
        </div>
    @else
        <div class="bg-slate-0 border border-slate-100 rounded-md overflow-hidden">
            <table class="w-full text-body-md">
                <thead class="bg-slate-50 text-body-sm text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Order</th>
                        <th class="text-left px-4 py-3 font-medium">Placed</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-right px-4 py-3 font-medium">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3 font-mono text-body-sm">{{ $order->order_number }}</td>
                            <td class="px-4 py-3">{{ $order->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ $order->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">${{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('dashboard.orders.show', $order) }}" class="inline-flex items-center gap-1 text-blue-600 hover:underline">View<x-icon name="chevron-right" class="w-4 h-4" /></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.dashboard>

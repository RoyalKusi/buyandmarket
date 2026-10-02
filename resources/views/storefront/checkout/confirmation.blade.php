<x-layouts.storefront title="Order confirmed">
    <div class="mx-auto max-w-[640px] px-4 md:px-6 py-10">
        <h1 class="text-heading-lg text-slate-900">Thank you — your order is confirmed</h1>
        <p class="mt-2 text-body-md text-slate-600">Order <span class="font-mono text-slate-900">{{ $order->order_number }}</span></p>

        <div class="mt-6 space-y-4">
            @foreach ($order->orderGroups as $orderGroup)
                <div class="border border-slate-200 rounded-md p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-body-md font-medium text-slate-900">{{ $orderGroup->seller->business_name }}</p>
                        <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ $orderGroup->status }}</span>
                    </div>
                    <ul class="space-y-1 mb-2">
                        @foreach ($orderGroup->items as $item)
                            <li class="text-body-sm text-slate-600">{{ $item->variant->product->title }} &times; {{ $item->quantity }}</li>
                        @endforeach
                    </ul>
                    @if ($orderGroup->shipment)
                        <p class="text-body-sm text-slate-500">Delivery: {{ $orderGroup->shipment->status }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        @auth
            <a href="{{ route('dashboard.orders.show', $order) }}" class="mt-6 inline-block h-12 leading-[48px] px-6 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">
                Track this order
            </a>
        @else
            {{-- Design System §6.6: account creation is a post-purchase,
                 one-field (password only — email/phone already captured)
                 upsell, never a pre-purchase gate. --}}
            <div class="mt-8 border-t border-slate-100 pt-6">
                <h2 class="text-heading-sm text-slate-900 mb-2">Want to track this order next time you visit?</h2>
                <p class="text-body-sm text-slate-600 mb-3">Set a password for {{ $order->guest_email }} and we'll create your account.</p>

                @if ($errors->any())
                    <p class="text-body-sm text-red-600 mb-2">{{ $errors->first() }}</p>
                @endif

                <form method="POST" action="{{ route('storefront.checkout.upsell', $order) }}" class="flex gap-2">
                    @csrf
                    <input type="password" name="password" placeholder="Choose a password" required class="flex-1 h-12 rounded-sm border border-slate-200 px-3 text-body-lg">
                    <button type="submit" class="h-12 px-6 rounded-sm border border-blue-600 text-blue-600 text-button font-semibold hover:bg-blue-50">Create account</button>
                </form>
            </div>
        @endauth
    </div>
</x-layouts.storefront>

<x-layouts.storefront title="Checkout — Payment">
    <div class="mx-auto max-w-[640px] px-4 md:px-6 py-10">
        <x-checkout-steps current="payment" />

        <h1 class="mt-6 text-heading-lg text-slate-900">Review &amp; pay</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-sm border border-red-600 bg-red-50 p-3 text-body-sm text-red-700">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 border border-slate-200 rounded-md p-4 space-y-4">
            @foreach ($groups as $storeId => $items)
                <div>
                    <p class="text-body-md font-medium text-slate-900">{{ $items->first()->variant->product->store->name }}</p>
                    <ul class="mt-1 space-y-1">
                        @foreach ($items as $item)
                            <li class="flex justify-between text-body-sm text-slate-600">
                                <span>{{ $item->variant->product->title }} &times; {{ $item->quantity }}</span>
                                <span class="tabular-nums">${{ number_format((float) $item->price_snapshot * $item->quantity, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @php($fee = $checkoutSession->delivery_selection[$storeId]['fee'] ?? null)
                    @if ($fee !== null)
                        <p class="flex justify-between text-body-sm text-slate-600 mt-1">
                            <span>Delivery</span>
                            <span class="tabular-nums">${{ number_format((float) $fee, 2) }}</span>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('storefront.checkout.payment.store', $checkoutSession) }}" class="mt-6 space-y-3">
            @csrf
            <p class="text-body-md text-slate-700 mb-2">Payment method</p>
            @foreach ($providers as $provider)
                <label class="flex items-center border border-slate-200 rounded-sm p-3 cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                    <input type="radio" name="provider" value="{{ $provider }}" class="mr-2" {{ $loop->first ? 'checked' : '' }}>
                    <span class="text-body-md text-slate-900 capitalize">{{ $provider }}</span>
                </label>
            @endforeach

            <button type="submit" class="w-full h-12 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">Place order</button>
        </form>
    </div>
</x-layouts.storefront>

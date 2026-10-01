<x-layouts.storefront title="Checkout — Delivery method">
    <div class="mx-auto max-w-[640px] px-4 md:px-6 py-10">
        <x-checkout-steps current="delivery" />

        <h1 class="mt-6 text-heading-lg text-slate-900">Delivery method</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-sm border border-red-600 bg-red-50 p-3 text-body-sm text-red-700">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('storefront.checkout.delivery.store', $checkoutSession) }}" class="mt-6 space-y-6">
            @csrf
            @foreach ($groups as $storeId => $items)
                <div class="border border-slate-200 rounded-md p-4">
                    <p class="text-body-md font-medium text-slate-900 mb-3">{{ $items->first()->variant->product->store->name }}</p>

                    @php($rateCards = $rateCardsByStore->get($storeId))
                    @if ($rateCards->isEmpty())
                        <p class="text-body-sm text-red-600">This seller has no delivery options configured yet.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($rateCards as $rateCard)
                                <label class="flex items-center justify-between border border-slate-200 rounded-sm p-3 cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                                    <span>
                                        <input type="radio" name="selection[{{ $storeId }}]" value="{{ $rateCard->id }}" class="mr-2" {{ $loop->first ? 'checked' : '' }}>
                                        <span class="text-body-md text-slate-900 capitalize">{{ $rateCard->method }}</span>
                                        <span class="block text-body-sm text-slate-500 ml-5">{{ $rateCard->zone->name }} &middot; {{ $rateCard->eta_min_days }}&ndash;{{ $rateCard->eta_max_days }} days</span>
                                    </span>
                                    <span class="text-body-md tabular-nums text-slate-900">${{ number_format((float) $rateCard->base_fee, 2) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            <button type="submit" class="w-full h-12 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">Continue</button>
        </form>
    </div>
</x-layouts.storefront>

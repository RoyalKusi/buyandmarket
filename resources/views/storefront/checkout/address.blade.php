<x-layouts.storefront title="Checkout — Delivery address">
    <div class="mx-auto max-w-[640px] px-4 md:px-6 py-10">
        <x-checkout-steps current="address" />

        <h1 class="mt-6 text-heading-lg text-slate-900">Delivery address</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-sm border border-red-600 bg-red-50 p-3 text-body-sm text-red-700">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($addresses->isNotEmpty())
            <form method="POST" action="{{ route('storefront.checkout.address.store', $checkoutSession) }}" class="mt-6 space-y-3">
                @csrf
                @foreach ($addresses as $address)
                    <label class="block border border-slate-200 rounded-md p-4 cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                        <input type="radio" name="address_id" value="{{ $address->id }}" class="mr-2" {{ $loop->first ? 'checked' : '' }}>
                        <span class="text-body-md text-slate-900 font-medium">{{ $address->label }}</span>
                        <span class="block text-body-sm text-slate-600 mt-1">{{ $address->recipient_name }} &middot; {{ $address->phone }}</span>
                        <span class="block text-body-sm text-slate-600">{{ $address->street_address }}, {{ $address->area }}, {{ $address->city }}, {{ $address->province }}</span>
                    </label>
                @endforeach
                <button type="submit" class="w-full h-12 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">Continue</button>
            </form>

            <p class="mt-6 text-body-sm text-slate-500">Or add a new address below.</p>
        @endif

        <form method="POST" action="{{ route('storefront.checkout.address.store', $checkoutSession) }}" class="mt-4 space-y-3">
            @csrf
            @guest
                <div>
                    <label class="block text-body-sm text-slate-700 mb-1">Email</label>
                    <input type="email" name="guest_email" required value="{{ old('guest_email') }}" class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg">
                </div>
            @endguest

            @foreach ([
                'label' => 'Label (e.g. Home)',
                'recipient_name' => 'Recipient name',
                'phone' => 'Phone',
                'province' => 'Province',
                'city' => 'City',
                'area' => 'Area',
                'street_address' => 'Street address',
            ] as $field => $label)
                <div>
                    <label class="block text-body-sm text-slate-700 mb-1">{{ $label }}</label>
                    <input name="{{ $field }}" value="{{ old($field) }}" class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg">
                </div>
            @endforeach

            <button type="submit" class="w-full h-12 rounded-sm border border-blue-600 text-blue-600 text-button font-semibold hover:bg-blue-50">
                {{ $addresses->isNotEmpty() ? 'Use this new address' : 'Continue' }}
            </button>
        </form>
    </div>
</x-layouts.storefront>

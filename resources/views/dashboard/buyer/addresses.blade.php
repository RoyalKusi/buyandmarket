<x-layouts.dashboard title="Addresses" active="addresses">
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
            @forelse ($addresses as $address)
                <div class="bg-slate-0 border border-slate-100 rounded-md p-4 flex items-start justify-between gap-4">
                    <div class="flex gap-3">
                        <x-icon name="map-pin" class="w-5 h-5 shrink-0 text-slate-400 mt-0.5" />
                        <div>
                            <p class="text-body-md font-medium text-slate-900">
                                {{ $address->label }}
                                @if ($address->is_default)
                                    <span class="ml-2 inline-flex items-center rounded-xs px-2 py-0.5 text-caption bg-blue-50 text-blue-600">Default</span>
                                @endif
                            </p>
                            <p class="text-body-sm text-slate-600">{{ $address->recipient_name }} &middot; {{ $address->phone }}</p>
                            <p class="text-body-sm text-slate-600">{{ $address->street_address }}, {{ $address->area }}, {{ $address->city }}, {{ $address->province }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('dashboard.addresses.destroy', $address) }}" onsubmit="return confirm('Remove this address?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1 text-body-sm text-red-600 hover:underline"><x-icon name="trash" class="w-4 h-4" />Remove</button>
                    </form>
                </div>
            @empty
                <div class="text-center py-16">
                    <x-icon name="map-pin" class="w-12 h-12 mx-auto text-slate-300" />
                    <p class="mt-3 text-body-lg text-slate-500">No saved addresses yet.</p>
                </div>
            @endforelse
        </div>

        <div class="bg-slate-0 border border-slate-100 rounded-md p-6 h-fit">
            <h2 class="text-heading-sm font-display text-slate-900 mb-4">Add an address</h2>
            <form method="POST" action="{{ route('dashboard.addresses.store') }}" class="space-y-3">
                @csrf
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
                        <label for="{{ $field }}" class="block text-body-sm text-slate-700 mb-1">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md focus:border-blue-600 focus:outline-none">
                    </div>
                @endforeach

                <label class="flex items-center gap-2 text-body-sm text-slate-600">
                    <input type="checkbox" name="is_default" value="1" class="rounded-xs border-slate-200">
                    Set as default
                </label>

                <button type="submit" class="w-full h-10 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">Save address</button>
            </form>
        </div>
    </div>
</x-layouts.dashboard>

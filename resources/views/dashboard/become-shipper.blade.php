<x-layouts.dashboard title="Become a shipper" active="become-shipper">
    @if ($shipper)
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6 max-w-xl">
            <p class="text-caption uppercase tracking-wide text-green-600 mb-1">✓ Active</p>
            <h2 class="text-heading-sm font-display text-slate-900">You're a shipper</h2>
            <p class="text-body-sm text-slate-500 mt-1">See the Shipper section in the sidebar to start claiming deliveries.</p>
        </div>
    @else
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6 max-w-xl">
            {{-- TDD §3.4 module 23: shipper registration is self-service
                 and immediately active — no KYC/stepper, unlike seller
                 onboarding. --}}
            <h2 class="text-heading-sm font-display text-slate-900 mb-2">Deliver for BuyAndMarket</h2>
            <p class="text-body-md text-slate-600 mb-4">Registration is immediate — you'll be able to claim deliveries right away.</p>

            <form method="POST" action="{{ route('dashboard.become-shipper.register') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="business_name" class="block text-body-md text-slate-700 mb-1">Business name (optional)</label>
                    <input id="business_name" type="text" name="business_name" value="{{ old('business_name') }}"
                        class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
                </div>
                <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Become a shipper</button>
            </form>
        </div>
    @endif
</x-layouts.dashboard>

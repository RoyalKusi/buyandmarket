<x-layouts.dashboard title="Become a seller" active="become-seller">
    {{-- TDD §3.1 module 4: "Stepper: business info -> KYC documents ->
         bank/payout details -> store setup -> first product -> admin
         review; resumable, each step's completion timestamped." Every
         step here posts to the same App\Services\SellerOnboardingService
         the API uses (App\Http\Controllers\Dashboard\
         SellerOnboardingController) — no business logic duplicated. --}}
    @if (! $seller)
        <div class="bg-slate-0 border border-slate-100 rounded-md p-6 max-w-xl">
            <h2 class="text-heading-sm font-display text-slate-900 mb-2">Start selling on BuyAndMarket</h2>
            <p class="text-body-md text-slate-600 mb-4">Tell us your business name to create your seller account. You'll complete KYC, payout details and your store setup next.</p>

            <form method="POST" action="{{ route('dashboard.become-seller.register') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="business_name" class="block text-body-md text-slate-700 mb-1">Business name</label>
                    <input id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" required
                        class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
                </div>
                <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Create seller account</button>
            </form>
        </div>
    @else
        @php($steps = $seller->onboardingSteps->keyBy('step'))
        @php($requiredKycTypes = ['national_id', 'proof_of_address'])
        @php($submittedKycTypes = $seller->kycDocuments->pluck('type')->unique())

        <div class="max-w-2xl space-y-4">
            <div class="bg-slate-0 border border-slate-100 rounded-md p-4 flex items-center justify-between">
                <div>
                    <p class="text-body-md text-slate-900 font-semibold">{{ $seller->business_name }}</p>
                    <p class="text-body-sm text-slate-500">Status: <span class="capitalize">{{ str($seller->status)->replace('_', ' ') }}</span></p>
                </div>
                <span class="inline-flex items-center rounded-xs px-2 py-1 text-caption bg-slate-100 text-slate-700 capitalize">{{ str($seller->status)->replace('_', ' ') }}</span>
            </div>

            {{-- Step: business info --}}
            <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                <p class="text-caption uppercase tracking-wide text-green-600 mb-1">✓ Done</p>
                <h3 class="text-heading-sm font-display text-slate-900">Business info</h3>
            </div>

            {{-- Step: KYC documents --}}
            <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                @if ($steps->get('kyc_documents')?->isComplete())
                    <p class="text-caption uppercase tracking-wide text-green-600 mb-1">✓ Done</p>
                    <h3 class="text-heading-sm font-display text-slate-900">KYC documents</h3>
                @else
                    <h3 class="text-heading-sm font-display text-slate-900 mb-1">KYC documents</h3>
                    <p class="text-body-sm text-slate-500 mb-4">Upload a national ID and proof of address (required); business registration is optional.</p>
                    <form method="POST" action="{{ route('dashboard.become-seller.kyc-documents') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-body-sm text-slate-700 mb-1">National ID {{ $submittedKycTypes->contains('national_id') ? '(already uploaded)' : '' }}</label>
                            <input type="file" name="national_id" class="w-full text-body-sm">
                        </div>
                        <div>
                            <label class="block text-body-sm text-slate-700 mb-1">Proof of address {{ $submittedKycTypes->contains('proof_of_address') ? '(already uploaded)' : '' }}</label>
                            <input type="file" name="proof_of_address" class="w-full text-body-sm">
                        </div>
                        <div>
                            <label class="block text-body-sm text-slate-700 mb-1">Business registration (optional)</label>
                            <input type="file" name="business_registration" class="w-full text-body-sm">
                        </div>
                        <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Upload</button>
                    </form>
                @endif
            </div>

            {{-- Step: bank details --}}
            <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                @if ($steps->get('bank_details')?->isComplete())
                    <p class="text-caption uppercase tracking-wide text-green-600 mb-1">✓ Done</p>
                    <h3 class="text-heading-sm font-display text-slate-900">Payout details</h3>
                @else
                    <h3 class="text-heading-sm font-display text-slate-900 mb-4">Payout details</h3>
                    <form method="POST" action="{{ route('dashboard.become-seller.payout-details') }}" class="space-y-3">
                        @csrf
                        <input type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="Bank name" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        <input type="text" name="account_name" value="{{ old('account_name') }}" placeholder="Account name" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        <input type="text" name="account_number" value="{{ old('account_number') }}" placeholder="Account number" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Save</button>
                    </form>
                @endif
            </div>

            {{-- Step: store setup --}}
            <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                @if ($steps->get('store_setup')?->isComplete())
                    <p class="text-caption uppercase tracking-wide text-green-600 mb-1">✓ Done</p>
                    <h3 class="text-heading-sm font-display text-slate-900">Store &mdash; {{ $seller->store?->name }}</h3>
                @else
                    <h3 class="text-heading-sm font-display text-slate-900 mb-4">Set up your store</h3>
                    <form method="POST" action="{{ route('dashboard.become-seller.store') }}" class="space-y-3">
                        @csrf
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Store name" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        <input type="text" name="slug" value="{{ old('slug') }}" placeholder="store-url-slug" required class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        <p class="text-body-sm text-slate-500">The slug cannot change once your store has been live for 30 days.</p>
                        <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Create store</button>
                    </form>
                @endif
            </div>

            {{-- Step: first product --}}
            <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                @if ($steps->get('first_product')?->isComplete())
                    <p class="text-caption uppercase tracking-wide text-green-600 mb-1">✓ Done</p>
                    <h3 class="text-heading-sm font-display text-slate-900">First product</h3>
                @elseif ($seller->store)
                    <h3 class="text-heading-sm font-display text-slate-900 mb-1">Add your first product</h3>
                    <p class="text-body-sm text-slate-500 mb-4">Your store is ready &mdash; create a product to complete this step.</p>
                    <a href="{{ route('seller.dashboard.products.create') }}" class="inline-block h-10 leading-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Create a product</a>
                @else
                    <h3 class="text-heading-sm font-display text-slate-500 mb-1">First product</h3>
                    <p class="text-body-sm text-slate-500">Finish setting up your store first.</p>
                @endif
            </div>

            {{-- Step: submit for review --}}
            <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                @if ($seller->status !== 'pending')
                    <p class="text-caption uppercase tracking-wide text-blue-600 mb-1">Submitted</p>
                    <h3 class="text-heading-sm font-display text-slate-900">Awaiting admin review</h3>
                    <p class="text-body-sm text-slate-500 mt-1">We'll notify you once your account is approved.</p>
                @else
                    <h3 class="text-heading-sm font-display text-slate-900 mb-1">Submit for review</h3>
                    <p class="text-body-sm text-slate-500 mb-4">Once every step above is complete, request admin review to start selling.</p>
                    <form method="POST" action="{{ route('dashboard.become-seller.submit-for-review') }}">
                        @csrf
                        <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Submit for review</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</x-layouts.dashboard>

<x-layouts.guest title="Verify your email — BuyAndMarket">
    <h1 class="text-heading-lg font-display text-slate-900 mb-2">Verify your email address</h1>
    <p class="text-body-md text-slate-600 mb-6">
        Thanks for signing up! Before getting started, please verify your email address by clicking the link we just emailed to you. If you didn't receive the email, we'll gladly send you another.
    </p>

    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 rounded-sm border border-green-600 bg-green-50 p-3 text-body-sm text-green-700">
            A new verification link has been sent to the email address you provided during registration.
        </div>
    @endif

    <div class="flex items-center gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="h-12 rounded-sm bg-blue-600 px-4 text-slate-0 text-button font-semibold hover:bg-blue-500">
                Resend verification email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-body-md text-slate-600 underline hover:text-slate-900">
                Log out
            </button>
        </form>
    </div>
</x-layouts.guest>

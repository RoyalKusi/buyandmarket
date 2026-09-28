<x-layouts.guest title="Create your account — BuyAndMarket">
    <h1 class="text-heading-lg font-display text-slate-900 mb-6">Create your account</h1>

    @if ($errors->any())
        <div class="mb-4 rounded-sm border border-red-600 bg-red-50 p-3 text-body-sm text-red-700">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Design System §6.7: sign-in/create-account share one screen and
         one shell; TDD §3.1 module 1: everyone registers as a buyer
         first — seller/shipper registration (both self-service too) is
         a separate, later step from the dashboard, never a choice made
         here. --}}
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-body-md text-slate-700 mb-1">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <div>
            <label for="email" class="block text-body-md text-slate-700 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <div>
            <label for="password" class="block text-body-md text-slate-700 mb-1">Password</label>
            <input id="password" type="password" name="password" required
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <div>
            <label for="password_confirmation" class="block text-body-md text-slate-700 mb-1">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <button type="submit" class="w-full h-12 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500 transition-colors duration-fast">
            Create account
        </button>
    </form>

    <p class="mt-6 text-center text-body-md text-slate-600">
        Already have an account?
        <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Sign in</a>
    </p>
</x-layouts.guest>

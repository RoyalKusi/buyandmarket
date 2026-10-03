<x-layouts.guest title="Sign in — BuyAndMarket">
    <h1 class="text-heading-lg font-display text-slate-900 mb-6">Sign in</h1>

    @if ($errors->any())
        <div class="mb-4 rounded-sm border border-red-600 bg-red-50 p-3 text-body-sm text-red-700">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-body-md text-slate-700 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <div>
            <label for="password" class="block text-body-md text-slate-700 mb-1">Password</label>
            <input id="password" type="password" name="password" required
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <label class="flex items-center gap-2 text-body-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded-xs border-slate-200">
            Remember me
        </label>

        <button type="submit" class="w-full h-12 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500 transition-colors duration-fast">
            Sign in
        </button>
    </form>

    <p class="mt-6 text-center text-body-md text-slate-600">
        New to BuyAndMarket?
        <a href="{{ route('register') }}" class="text-blue-600 hover:underline">Create an account</a>
    </p>

    <x-social-login-buttons />
</x-layouts.guest>

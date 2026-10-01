<x-layouts.guest title="Confirm password — BuyAndMarket">
    <h1 class="text-heading-lg font-display text-slate-900 mb-2">Confirm your password</h1>
    <p class="text-body-md text-slate-600 mb-6">This is a sensitive action — please confirm your password to continue.</p>

    @if ($errors->any())
        <div class="mb-4 rounded-sm border border-red-600 bg-red-50 p-3 text-body-sm text-red-700">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="password" class="block text-body-md text-slate-700 mb-1">Password</label>
            <input id="password" type="password" name="password" required autofocus
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <button type="submit" class="w-full h-12 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">
            Confirm
        </button>
    </form>
</x-layouts.guest>

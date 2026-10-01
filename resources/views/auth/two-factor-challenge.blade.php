<x-layouts.guest title="Two-factor verification — BuyAndMarket">
    <h1 class="text-heading-lg font-display text-slate-900 mb-2">Two-factor verification</h1>
    <p class="text-body-md text-slate-600 mb-6">Enter the code from your authenticator app, or a recovery code.</p>

    @if ($errors->any())
        <div class="mb-4 rounded-sm border border-red-600 bg-red-50 p-3 text-body-sm text-red-700">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.login.store') }}" x-data="{ recovery: false }" class="space-y-4">
        @csrf

        <div x-show="! recovery">
            <label for="code" class="block text-body-md text-slate-700 mb-1">Authentication code</label>
            <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <div x-show="recovery" style="display: none">
            <label for="recovery_code" class="block text-body-md text-slate-700 mb-1">Recovery code</label>
            <input id="recovery_code" type="text" name="recovery_code"
                class="w-full h-12 rounded-sm border border-slate-200 px-3 text-body-lg focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </div>

        <button type="submit" class="w-full h-12 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">
            Verify
        </button>

        <button type="button" x-on:click="recovery = ! recovery" class="w-full text-center text-body-sm text-blue-600 hover:underline">
            <span x-show="! recovery">Use a recovery code instead</span>
            <span x-show="recovery" style="display: none">Use an authentication code instead</span>
        </button>
    </form>
</x-layouts.guest>

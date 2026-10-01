<div class="bg-slate-0 border border-slate-100 rounded-md p-6 max-w-[480px]">
    @if ($user->hasEnabledTwoFactorAuthentication())
        <p class="text-body-md text-green-700 mb-4">Two-factor authentication is enabled.</p>

        @if (! empty($recoveryCodes))
            <div class="mb-4">
                <p class="text-body-sm text-slate-700 mb-2">Recovery codes — store these somewhere safe:</p>
                <div class="bg-slate-50 rounded-sm p-3 font-mono text-body-sm space-y-1">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <div>{{ $recoveryCode }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <button type="button" wire:click="disable" wire:confirm="Disable two-factor authentication?" class="h-10 rounded-sm border border-red-600 text-red-600 px-4 text-button font-semibold hover:bg-red-50">
            Disable
        </button>
    @elseif ($enabling)
        <p class="text-body-md text-slate-700 mb-3">Scan this code with your authenticator app, then enter the 6-digit code to confirm.</p>
        <div class="mb-4">{!! $qrCodeSvg !!}</div>

        @error('code')
            <p class="text-body-sm text-red-600 mb-2">{{ $message }}</p>
        @enderror

        <form wire:submit="confirm" class="flex gap-2">
            <input type="text" wire:model="code" inputmode="numeric" placeholder="123456" class="flex-1 h-10 rounded-sm border border-slate-200 px-3 text-body-md">
            <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Confirm</button>
        </form>
    @else
        <p class="text-body-md text-slate-700 mb-4">Two-factor authentication is not enabled.</p>
        <button type="button" wire:click="enable" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">
            Enable two-factor authentication
        </button>
    @endif
</div>

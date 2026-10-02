<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Component;

/**
 * TDD §8.2: "MFA required for seller (financial settlement access) and
 * all admin/sub-admin roles; TOTP-based ... as the primary method."
 * Fortify's enable/confirm/disable actions existed since Run 1.1 but had
 * no UI calling them — this is that UI, plus App\Http\Middleware\
 * EnsureTwoFactorEnabled is what actually makes it required rather than
 * merely available.
 */
class TwoFactorSetup extends Component
{
    public bool $enabling = false;

    public string $code = '';

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());
        $this->enabling = true;
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $confirm(Auth::user(), $this->code);
        $this->enabling = false;
        $this->code = '';
    }

    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());
        $this->enabling = false;
    }

    public function render()
    {
        $user = Auth::user()->fresh();

        return view('livewire.two-factor-setup', [
            'user' => $user,
            'qrCodeSvg' => $this->enabling ? $user->twoFactorQrCodeSvg() : null,
            'recoveryCodes' => $user->hasEnabledTwoFactorAuthentication() ? $user->recoveryCodes() : [],
        ]);
    }
}

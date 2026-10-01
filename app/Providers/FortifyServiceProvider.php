<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Design System §6.7: single-screen, tab-switchable sign-in/
        // create-account — implemented here as two thin views sharing one
        // guest layout, since the register.store/login.store POST
        // handlers (Fortify's own controllers) already worked and were
        // tested (tests/Feature/Auth/RegistrationTest.php) before these
        // GET views existed to reach them from a browser.
        Fortify::loginView('auth.login');
        Fortify::registerView('auth.register');

        // TDD §8.2: TOTP two-factor is registered (config/fortify.php)
        // with 'confirmPassword' => true, which gates every two-factor
        // management route behind Fortify's own 'password.confirm'
        // middleware — both of these views were missing entirely (same
        // class of gap Run 1.7 found with login/register): a seller/admin
        // trying to set up 2FA, or a user with 2FA enabled trying to log
        // in, would 500 on a missing view binding.
        Fortify::twoFactorChallengeView('auth.two-factor-challenge');
        Fortify::confirmPasswordView('auth.confirm-password');

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}

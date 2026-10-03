<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialAccountResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Throwable;

/**
 * Web "Continue with Google/Facebook" — the real server-redirect OAuth
 * flow Socialite is built for: the browser is sent to the provider,
 * the provider sends it back here with a code Socialite exchanges for
 * the user's profile. Shares App\Services\SocialAccountResolver's
 * find-or-create logic with the mobile API's token-based equivalent
 * (App\Http\Controllers\Api\V1\SocialAuthController) — same account
 * rules, different OAuth flow for a browser session vs. a Bearer-token
 * client with no session to redirect through.
 */
class SocialLoginController extends Controller
{
    public function __construct(private readonly SocialAccountResolver $resolver) {}

    public function redirect(string $provider): RedirectResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return $driver->redirectUrl(route('social.callback', $provider))->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver($provider);

            $socialUser = $driver->redirectUrl(route('social.callback', $provider))->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors([
                'email' => 'Could not sign in with '.ucfirst($provider).'. Please try again.',
            ]);
        }

        if ($provider === 'facebook' && $socialUser->getEmail() === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'This Facebook account has no accessible email address. Please sign in another way.',
            ]);
        }

        $idColumn = $provider === 'google' ? 'google_id' : 'facebook_id';
        $user = $this->resolver->resolve($socialUser, $idColumn);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}

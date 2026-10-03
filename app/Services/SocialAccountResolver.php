<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Shared by the mobile API's token-based sign-in
 * (App\Http\Controllers\Api\V1\SocialAuthController) and the web's
 * redirect-based one (App\Http\Controllers\Auth\SocialLoginController)
 * — both end up with a verified Socialite user and need the exact same
 * find-or-create account logic, just reached via different OAuth flows
 * (a mobile client has no browser session to redirect through; a
 * browser has no access token to hand the server up front).
 */
class SocialAccountResolver
{
    /**
     * Matched first by the provider's own ID (a returning social-login
     * user); falling back to linking an existing email/password account
     * by email (the same person signing in a different way); otherwise a
     * brand-new buyer account. Either provider having already
     * authenticated the person is treated as email ownership proof, same
     * trust level as clicking a verification link.
     */
    public function resolve(SocialiteUser $socialUser, string $idColumn): User
    {
        $user = User::where($idColumn, $socialUser->getId())->first();

        if ($user !== null) {
            return $user;
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user !== null) {
            $user->forceFill([
                $idColumn => $socialUser->getId(),
                'avatar_url' => $user->avatar_url ?? $socialUser->getAvatar(),
            ])->save();

            return $user;
        }

        $user = User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'BuyAndMarket buyer',
            'email' => $socialUser->getEmail(),
            $idColumn => $socialUser->getId(),
            'avatar_url' => $socialUser->getAvatar(),
            // Unusable for password login (never returned to the client,
            // never matched by Hash::check against anything a person
            // could type) — this account only ever signs in through this
            // same provider, exactly like a password-registered account
            // always has a real password.
            'password' => Hash::make(Str::random(40)),
        ]);

        // email_verified_at isn't mass-assignable (by design — it's never
        // meant to be settable from ordinary request input), so it's set
        // separately here rather than silently dropped by $fillable.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole('buyer');

        event(new Registered($user));

        return $user;
    }
}

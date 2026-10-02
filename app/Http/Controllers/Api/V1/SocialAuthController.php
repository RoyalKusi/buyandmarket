<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Throwable;

/**
 * Mobile Google/Facebook sign-in. The app performs native sign-in
 * on-device (google_sign_in / flutter_facebook_auth) and sends the
 * resulting provider access token here — this is deliberately NOT the
 * server-initiated redirect flow Socialite is usually used for; it
 * only uses Socialite's stateless `userFromToken()` to verify a token
 * the app already has and fetch the provider's profile for it, via
 * each provider's own userinfo/Graph API endpoint.
 *
 * Shares `AuthController::issueToken()`'s exact response shape
 * (`{data, token}`) so the mobile client's existing
 * `AppUser.fromJson`/token-storage code needs no branching between an
 * email/password login and a social one.
 */
class SocialAuthController extends Controller
{
    public function google(Request $request): JsonResponse
    {
        $data = $request->validate(['access_token' => ['required', 'string']]);

        $socialUser = $this->resolveSocialUser('google', $data['access_token']);

        $user = $this->findOrCreateUser($socialUser, 'google_id');

        return response()->json([
            'data' => $user,
            'token' => $this->issueToken($request, $user),
        ]);
    }

    public function facebook(Request $request): JsonResponse
    {
        $data = $request->validate(['access_token' => ['required', 'string']]);

        $socialUser = $this->resolveSocialUser('facebook', $data['access_token']);

        if ($socialUser->getEmail() === null) {
            // Facebook only returns an email when the person granted the
            // 'email' permission and has one on file — without it there's
            // nothing to match an existing account against or verify.
            throw ValidationException::withMessages([
                'access_token' => 'This Facebook account has no accessible email address. Please sign in another way.',
            ]);
        }

        $user = $this->findOrCreateUser($socialUser, 'facebook_id');

        return response()->json([
            'data' => $user,
            'token' => $this->issueToken($request, $user),
        ]);
    }

    private function resolveSocialUser(string $provider, string $accessToken): SocialiteUser
    {
        try {
            // Both configured drivers (google, facebook) are OAuth2 and
            // resolve to AbstractProvider; the Contracts\Provider this is
            // typed against covers OAuth1 too and doesn't declare
            // stateless()/userFromToken().
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver($provider);

            return $driver->stateless()->userFromToken($accessToken);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'access_token' => "Could not verify this {$provider} account.",
            ]);
        }
    }

    /**
     * Matched first by the provider's own ID (a returning social-login
     * user); falling back to linking an existing email/password account
     * by email (the same person signing in a different way); otherwise a
     * brand-new buyer account. Either provider having already
     * authenticated the person is treated as email ownership proof, same
     * trust level as clicking a verification link.
     */
    private function findOrCreateUser(SocialiteUser $socialUser, string $idColumn): User
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
            // same provider, exactly like AuthController-issued accounts
            // always have a real password.
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

    private function issueToken(Request $request, User $user): string
    {
        return $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;
    }
}

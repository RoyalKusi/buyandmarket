<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SocialAccountResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
 * each provider's own userinfo/Graph API endpoint. The web's own
 * sign-in (App\Http\Controllers\Auth\SocialLoginController) uses the
 * redirect flow instead, sharing App\Services\SocialAccountResolver's
 * find-or-create logic with this controller.
 *
 * Shares `AuthController::issueToken()`'s exact response shape
 * (`{data, token}`) so the mobile client's existing
 * `AppUser.fromJson`/token-storage code needs no branching between an
 * email/password login and a social one.
 */
class SocialAuthController extends Controller
{
    public function __construct(private readonly SocialAccountResolver $resolver) {}

    public function google(Request $request): JsonResponse
    {
        $data = $request->validate(['access_token' => ['required', 'string']]);

        $socialUser = $this->resolveSocialUser('google', $data['access_token']);

        $user = $this->resolver->resolve($socialUser, 'google_id');

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

        $user = $this->resolver->resolve($socialUser, 'facebook_id');

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

    private function issueToken(Request $request, User $user): string
    {
        return $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;
    }
}

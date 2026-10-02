<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Mobile/third-party token auth (TDD §7.1: "Bearer tokens for mobile/
 * API"). Fortify's own auth actions (CreateNewUser, ResetUserPassword)
 * are reused directly here rather than duplicated — this controller is
 * a thin token-issuing wrapper around the exact same account logic the
 * web login/register/reset-password forms already use, so a buyer's
 * password policy, role assignment, and reset flow never drift between
 * the two clients.
 *
 * Web login/register stay on Fortify's cookie-session flow (shared
 * dashboard, CSRF, Livewire) — this controller exists because a mobile
 * client has no session to share and needs a bearer token it can store
 * and send back on every request instead.
 */
class AuthController extends Controller
{
    public function register(Request $request, CreatesNewUsers $creator): JsonResponse
    {
        // CreatesNewUsers::create() is typed against Fortify's generic
        // contract return type; this app configures exactly one auth
        // provider (config/auth.php), so it's always the concrete model.
        /** @var User $user */
        $user = $creator->create($request->all());

        event(new Registered($user));

        return response()->json([
            'data' => $user,
            'token' => $this->issueToken($request, $user),
        ], 201);
    }

    /**
     * TDD §8.2's MFA requirement only applies to seller/admin dashboards
     * (App\Http\Middleware\EnsureTwoFactorEnabled) — those roles aren't
     * part of this mobile MVP's buyer-facing scope, so an account with
     * 2FA enabled is rejected here rather than half-supporting a TOTP
     * challenge the app has no screen for yet.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($data['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$this->secondsUntilAvailable($throttleKey)} seconds.",
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'email' => 'This account has two-factor authentication enabled and cannot sign in from the app yet — use buyandmarket.com instead.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        return response()->json([
            'data' => $user,
            'token' => $this->issueToken($request, $user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user('sanctum')]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always a 200 regardless of whether the email exists — same
        // enumeration-safe behaviour as Fortify's own web forgot-
        // password form, just without the redirect-with-flash-message
        // a JSON client has no use for.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function resetPassword(Request $request, ResetsUserPasswords $resetter): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = Password::reset(
            $data,
            function (User $user) use ($resetter, $data) {
                $resetter->reset($user, $data);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Password reset — sign in with your new password.']);
    }

    private function issueToken(Request $request, User $user): string
    {
        return $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;
    }

    private function secondsUntilAvailable(string $throttleKey): int
    {
        return RateLimiter::availableIn($throttleKey);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Mobile app groundwork: App\Http\Controllers\Api\V1\AuthController is
 * the token-issuing counterpart to Fortify's cookie-session web auth —
 * a mobile client has no session to share, so it registers/logs in here
 * and gets back a bearer token to store and send on every subsequent
 * request instead.
 */
class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_register_and_receives_a_bearer_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Tendai Moyo',
            'email' => 'tendai@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('token'));

        $user = User::where('email', 'tendai@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('buyer'));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rejects_a_weak_or_mismatched_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Tendai Moyo',
            'email' => 'tendai@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'tendai@example.com']);
    }

    public function test_a_registered_bearer_token_actually_authenticates_subsequent_requests(): void
    {
        $token = $this->postJson('/api/v1/auth/register', [
            'name' => 'Tendai Moyo',
            'email' => 'tendai@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'tendai@example.com');
    }

    public function test_a_buyer_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->withRole('buyer')->create(['password' => bcrypt('correct-password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame($user->id, $response->json('data.id'));
    }

    public function test_login_is_rejected_with_the_wrong_password(): void
    {
        $user = User::factory()->withRole('buyer')->create(['password' => bcrypt('correct-password')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone 15',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->withRole('buyer')->create(['password' => bcrypt('correct-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'device_name' => 'iPhone 15',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('Too many login attempts', $response->json('errors.email.0'));
    }

    public function test_an_account_with_two_factor_enabled_cannot_log_in_from_the_app_yet(): void
    {
        $user = User::factory()->withTwoFactorEnabled()->create(['password' => bcrypt('correct-password')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'iPhone 15',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_a_logged_in_user_can_log_out_and_the_token_stops_working(): void
    {
        $user = User::factory()->withRole('buyer')->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        // Sanctum's guard caches its resolved user for the lifetime of
        // the guard instance, which in a real request is exactly one
        // HTTP request — but Laravel's test client reuses the same
        // application (and so the same guard instance) across multiple
        // calls within one test method. forgetGuards() forces the next
        // call to re-resolve from the token's current DB state, the way
        // a brand new process would.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_forgot_password_sends_a_reset_link_without_revealing_whether_the_email_exists(): void
    {
        Notification::fake();

        $user = User::factory()->withRole('buyer')->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_with_a_valid_token_actually_changes_the_password(): void
    {
        $user = User::factory()->withRole('buyer')->create(['password' => bcrypt('old-password')]);
        $token = app('auth.password.broker')->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'brand-new-password',
            'device_name' => 'iPhone 15',
        ])->assertOk();
    }
}

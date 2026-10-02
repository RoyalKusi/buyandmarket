<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Mobile Google/Facebook sign-in (App\Http\Controllers\Api\V1\
 * SocialAuthController). The app performs native sign-in on-device and
 * sends the resulting access token here; this controller verifies it
 * via Socialite's stateless `userFromToken()` against the provider's
 * own userinfo/Graph API endpoint — Socialite's built-in `::fake()`
 * targets the redirect-callback `user()` method instead, so these tests
 * mock the facade's provider chain directly.
 */
class SocialAuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function mockProvider(string $driver, SocialiteUser $socialiteUser, string $expectedToken = 'a-real-provider-token'): void
    {
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('userFromToken')->once()->with($expectedToken)->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->once()->with($driver)->andReturn($provider);
    }

    private function fakeSocialiteUser(array $attributes = []): SocialiteUser
    {
        return SocialiteUser::fake(array_merge([
            'id' => 'provider-id-123',
            'name' => 'Tendai Moyo',
            'email' => 'tendai@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ], $attributes));
    }

    public function test_google_sign_in_creates_a_new_buyer_account(): void
    {
        $this->mockProvider('google', $this->fakeSocialiteUser());

        $response = $this->postJson('/api/v1/auth/google', ['access_token' => 'a-real-provider-token'])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'tendai@example.com',
            'google_id' => 'provider-id-123',
        ]);
        $user = User::where('email', 'tendai@example.com')->first();
        $this->assertTrue($user->hasRole('buyer'));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($response->json('token'));
    }

    public function test_google_sign_in_logs_in_an_existing_social_user(): void
    {
        $existing = User::factory()->withRole('buyer')->create(['google_id' => 'provider-id-123']);

        $this->mockProvider('google', $this->fakeSocialiteUser(['email' => $existing->email]));

        $response = $this->postJson('/api/v1/auth/google', ['access_token' => 'a-real-provider-token'])->assertOk();

        $this->assertSame($existing->id, $response->json('data.id'));
        $this->assertSame(1, User::where('email', $existing->email)->count());
    }

    public function test_google_sign_in_links_an_existing_password_account_by_email(): void
    {
        $existing = User::factory()->withRole('buyer')->create(['email' => 'tendai@example.com']);

        $this->mockProvider('google', $this->fakeSocialiteUser());

        $this->postJson('/api/v1/auth/google', ['access_token' => 'a-real-provider-token'])->assertOk();

        $this->assertSame('provider-id-123', $existing->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'tendai@example.com')->count());
    }

    public function test_facebook_sign_in_creates_a_new_buyer_account(): void
    {
        $this->mockProvider('facebook', $this->fakeSocialiteUser(['id' => 'fb-id-456']));

        $response = $this->postJson('/api/v1/auth/facebook', ['access_token' => 'a-real-provider-token'])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'tendai@example.com',
            'facebook_id' => 'fb-id-456',
        ]);
        $this->assertNotNull($response->json('token'));
    }

    public function test_facebook_sign_in_without_an_email_is_rejected(): void
    {
        $this->mockProvider('facebook', $this->fakeSocialiteUser(['email' => null]));

        $this->postJson('/api/v1/auth/facebook', ['access_token' => 'a-real-provider-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_token');
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('userFromToken')->once()->andThrow(new \Exception('invalid token'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'not-a-real-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_token');
    }
}

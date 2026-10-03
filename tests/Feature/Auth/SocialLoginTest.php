<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Web "Continue with Google/Facebook" (App\Http\Controllers\Auth\
 * SocialLoginController) — the server-redirect OAuth flow, sharing
 * App\Services\SocialAccountResolver's find-or-create logic with the
 * mobile API's token-based equivalent (already covered by
 * SocialAuthApiTest). These tests mock the facade's provider chain the
 * same way, since Socialite's built-in `::fake()` targets a plain
 * `user()` call too, just without the `redirectUrl()` chaining this
 * controller does.
 */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private function mockProvider(string $driver, SocialiteUser $socialiteUser): void
    {
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

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

    public function test_redirect_sends_the_browser_to_the_provider(): void
    {
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/auth/google/redirect')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_google_callback_logs_in_a_new_buyer_and_redirects_to_the_dashboard(): void
    {
        $this->mockProvider('google', $this->fakeSocialiteUser());

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'tendai@example.com', 'google_id' => 'provider-id-123']);
    }

    public function test_google_callback_logs_in_an_existing_social_user(): void
    {
        $existing = User::factory()->withRole('buyer')->create(['google_id' => 'provider-id-123']);

        $this->mockProvider('google', $this->fakeSocialiteUser(['email' => $existing->email]));

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existing);
    }

    public function test_facebook_callback_without_an_email_redirects_back_to_login_with_an_error(): void
    {
        $this->mockProvider('facebook', $this->fakeSocialiteUser(['email' => null]));

        $this->get('/auth/facebook/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_failed_provider_exchange_redirects_back_to_login_with_an_error(): void
    {
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andThrow(new \Exception('denied'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_already_authenticated_visitor_cannot_reach_the_social_routes(): void
    {
        $user = User::factory()->withRole('buyer')->create();

        $this->actingAs($user)->get('/auth/google/redirect')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get('/auth/google/callback')->assertRedirect(route('dashboard'));
    }
}

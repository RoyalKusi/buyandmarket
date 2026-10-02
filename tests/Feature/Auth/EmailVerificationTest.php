<?php

namespace Tests\Feature\Auth;

use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Production Readiness Report condition #3: email verification.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithDeliverableProduct(): ProductVariant
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['base_price' => '20.00']);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);
        $zone = DeliveryZone::factory()->create();
        DeliveryRateCard::factory()->for($seller)->for($zone, 'zone')->create([
            'method' => 'standard',
            'base_fee' => '4.50',
        ]);

        return $variant;
    }

    public function test_the_verification_notice_page_renders_for_an_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertOk();
        $response->assertSee('Verify your email address');
    }

    public function test_registering_dispatches_the_verification_email(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Tinashe Moyo',
            'email' => 'tinashe@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'tinashe@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_the_registered_event_is_wired_to_send_the_verification_notification(): void
    {
        // Laravel 11 has no EventServiceProvider stub, so this pairing is
        // registered by hand in AppServiceProvider::boot() — this test
        // guards against that wiring silently regressing.
        Event::fake();

        Event::assertListening(Registered::class, SendEmailVerificationNotification::class);
    }

    public function test_clicking_a_valid_verification_link_marks_the_email_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_an_invalid_hash_does_not_verify_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_unverified_authenticated_user_cannot_start_checkout(): void
    {
        $user = User::factory()->unverified()->create();
        $variant = $this->sellerWithDeliverableProduct();
        $cart = app(CartService::class)->getOrCreateCart($user, null);
        app(CartService::class)->addItem($cart, $variant, 1);

        $response = $this->actingAs($user)->get('/checkout/start');

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_a_verified_authenticated_user_can_start_checkout(): void
    {
        $user = User::factory()->create();
        $variant = $this->sellerWithDeliverableProduct();
        $cart = app(CartService::class)->getOrCreateCart($user, null);
        app(CartService::class)->addItem($cart, $variant, 1);

        $response = $this->actingAs($user)->get('/checkout/start');

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_an_unverified_user_cannot_reach_the_become_seller_page(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/dashboard/become-seller');

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_a_verified_user_can_reach_the_become_seller_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard/become-seller');

        $response->assertOk();
    }

    public function test_guest_checkout_is_unaffected_by_email_verification(): void
    {
        $variant = $this->sellerWithDeliverableProduct();

        $first = $this->get('/');
        $sessionCookieName = config('session.cookie');
        $sessionCookie = $first->getCookie($sessionCookieName);
        $this->withCookie($sessionCookieName, $sessionCookie->getValue());

        $cart = app(CartService::class)->getOrCreateCart(null, $sessionCookie->getValue());
        app(CartService::class)->addItem($cart, $variant, 1);

        $response = $this->get('/checkout/start');

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors('email');
    }
}

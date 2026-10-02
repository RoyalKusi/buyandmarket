<?php

namespace Tests\Feature\Commerce;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile-app groundwork finding: App\Http\Controllers\Api\V1\CartController,
 * CheckoutController and Ai\ConversationController all resolved the
 * acting user via the bare $request->user() — the default 'web' session
 * guard — rather than $request->user('sanctum'), the only guard that
 * also recognises a real HTTP Authorization: Bearer <token> request with
 * no session cookie. Every existing "authenticated buyer" test for these
 * endpoints used actingAs(), which (before Tests\TestCase's own
 * actingAs() override) only ever populated the 'web' guard — so the gap
 * went uncaught: those tests never actually sent a token over HTTP, and
 * $request->user() happened to resolve anyway via the session Laravel's
 * test client carries by default. A real mobile client sending nothing
 * but a Bearer token was silently treated as a guest.
 *
 * This test exercises the real thing — an actual issued Sanctum
 * personal access token, sent as a real Authorization header via
 * withToken(), no test-only auth shortcut — proving a genuine mobile
 * client can use the cart and checkout exactly as an authenticated
 * buyer, not as a guest.
 */
class MobileBearerTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bearer_token_client_adds_to_cart_as_the_token_owner_not_a_guest(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $token = $buyer->createToken('mobile')->plainTextToken;

        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['base_price' => '15.00']);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertCreated();

        $cartItem = CartItem::firstOrFail();
        $this->assertSame($buyer->id, $cartItem->cart->user_id);
        $this->assertNull($cartItem->cart->session_id);
    }

    public function test_a_bearer_token_client_sees_their_own_cart_not_a_fresh_guest_cart(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $token = $buyer->createToken('mobile')->plainTextToken;

        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();

        $response = $this->withToken($token)->getJson('/api/v1/carts')->assertOk();

        $this->assertSame($buyer->id, $response->json('data.user_id'));
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_a_bearer_token_client_can_complete_checkout_as_the_authenticated_buyer(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $token = $buyer->createToken('mobile')->plainTextToken;
        $address = Address::factory()->for($buyer)->create();

        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['base_price' => '20.00']);
        $variant = ProductVariant::factory()->for($product)->create(['price_override' => null, 'stock_quantity' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertCreated();

        $sessionId = $this->withToken($token)
            ->postJson('/api/v1/checkout/session')
            ->assertCreated()
            // Guest email is only required when there is no authenticated
            // user — this fails if the session ever gets created as a
            // guest session despite the Bearer token.
            ->json('data.id');

        $this->withToken($token)
            ->patchJson("/api/v1/checkout/session/{$sessionId}/address", ['address_id' => $address->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'address_selection');

        $this->assertDatabaseHas('checkout_sessions', ['id' => $sessionId, 'user_id' => $buyer->id]);
    }

    public function test_an_invalid_bearer_token_is_treated_as_a_guest_not_a_500(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $this->withToken('this-is-not-a-real-token')
            ->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertCreated();

        $cartItem = CartItem::firstOrFail();
        $this->assertNull($cartItem->cart->user_id);
        $this->assertNotNull($cartItem->cart->session_id);
    }
}

<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD §3.4 module 17: "merged on login." App\Services\CartService::
 * mergeIntoUserCart() existed since Run 1.5 but nothing ever called it
 * until App\Listeners\MergeGuestCartOnLogin (Run 1.12).
 */
class CartMergeOnLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cart_is_merged_into_the_users_cart_on_login(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $buyer = User::factory()->withRole('buyer')->create(['password' => bcrypt('a-strong-password')]);

        $first = $this->get('/');
        $sessionCookieName = config('session.cookie');
        $sessionCookie = $first->getCookie($sessionCookieName);
        $this->withCookie($sessionCookieName, $sessionCookie->getValue());

        $guestCart = app(CartService::class)->getOrCreateCart(null, $sessionCookie->getValue());
        app(CartService::class)->addItem($guestCart, $variant, 2);

        $this->post('/login', [
            'email' => $buyer->email,
            'password' => 'a-strong-password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($buyer);

        $userCart = Cart::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(2, $userCart->items()->sum('quantity'));
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
    }
}

<?php

namespace Tests\Unit\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_the_same_variant_twice_sums_the_quantity_and_refreshes_the_price_snapshot(): void
    {
        $variant = ProductVariant::factory()->for(
            Product::factory()->published()->create(['base_price' => '10.00'])
        )->create(['price_override' => null, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create();

        $service = app(CartService::class);
        $service->addItem($cart, $variant, 2);
        $item = $service->addItem($cart, $variant, 3);

        $this->assertSame(5, $item->quantity);
        $this->assertSame('10.00', (string) $item->price_snapshot);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_updating_quantity_to_zero_removes_the_item(): void
    {
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->create();

        app(CartService::class)->updateQuantity($item, 0);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_merging_a_guest_cart_into_a_user_cart_sums_overlapping_items_and_deletes_the_guest_cart(): void
    {
        $user = User::factory()->create();
        $userCart = Cart::factory()->create(['user_id' => $user->id, 'session_id' => null]);
        $variant = ProductVariant::factory()->create();
        $userCart->items()->create(['variant_id' => $variant->id, 'quantity' => 1, 'price_snapshot' => '10.00']);

        $guestCart = Cart::factory()->create();
        $guestCart->items()->create(['variant_id' => $variant->id, 'quantity' => 2, 'price_snapshot' => '10.00']);
        $otherVariant = ProductVariant::factory()->create();
        $guestCart->items()->create(['variant_id' => $otherVariant->id, 'quantity' => 1, 'price_snapshot' => '5.00']);

        $merged = app(CartService::class)->mergeIntoUserCart($guestCart, $user);

        $this->assertSame(3, $merged->items()->where('variant_id', $variant->id)->first()->quantity);
        $this->assertSame(1, $merged->items()->where('variant_id', $otherVariant->id)->first()->quantity);
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
    }
}

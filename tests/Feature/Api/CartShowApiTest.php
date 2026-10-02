<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found during a mobile-app visual QA pass: the mobile checkout screen
 * groups cart items by store (to show one delivery-method picker per
 * seller, matching the web checkout's own per-store delivery step) via
 * `item.product.store`. Api\V1\CartController::show() only ever
 * eager-loaded `items.variant.product` — never `.store` — so that field
 * was always null, the grouping produced zero groups, the delivery step
 * rendered empty, and "Continue to payment" sailed past a client-side
 * guard that compared two now-equal empty counts, sending an empty
 * `selection` straight into a 422 from CheckoutController::setDelivery().
 */
class CartShowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cart_items_response_includes_its_products_store(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $seller->store->update(['name' => 'Harare Tech Store']);
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 10]);

        $this->actingAs($buyer)->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();

        $response = $this->actingAs($buyer)->getJson('/api/v1/carts')->assertOk();

        $this->assertSame('Harare Tech Store', $response->json('data.items.0.variant.product.store.name'));
    }
}

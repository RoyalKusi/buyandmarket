<?php

namespace Tests\Feature\Commerce;

use App\Livewire\Storefront\AddToCartForm;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit finding (P2): neither CartService::addItem() nor
 * updateQuantity() capped the requested quantity against real stock —
 * the HTML min/max attributes are client-side only and a Livewire
 * request can set the bound `quantity` property directly. The checkout
 * money-path was already protected (assertCartIsPurchasable +
 * OrderService's locked re-check), but cart-add itself let a buyer
 * queue up an unbounded quantity, risking a confusing late failure or
 * a DB-level integer overflow instead of an immediate, clear one.
 */
class CartQuantityValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_more_than_available_stock_is_rejected_with_a_clear_error(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 3]);

        Livewire::test(AddToCartForm::class, ['product' => $product])
            ->set('variantId', $variant->id)
            ->set('quantity', 999999)
            ->call('addToCart')
            ->assertHasErrors('quantity');

        $this->assertDatabaseMissing('cart_items', ['variant_id' => $variant->id]);
    }

    public function test_the_api_rejects_a_cart_quantity_above_stock(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 2]);

        $this->actingAs($buyer)
            ->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 50])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_two_valid_adds_that_together_exceed_stock_are_rejected_on_the_second(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $this->actingAs($buyer)
            ->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 3])
            ->assertCreated();

        $this->actingAs($buyer)
            ->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 3])
            ->assertUnprocessable();

        $this->assertDatabaseHas('cart_items', ['variant_id' => $variant->id, 'quantity' => 3]);
    }
}

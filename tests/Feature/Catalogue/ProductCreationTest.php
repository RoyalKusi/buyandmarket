<?php

namespace Tests\Feature\Catalogue;

use App\Models\Category;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCreationTest extends TestCase
{
    use RefreshDatabase;

    private function variantPayload(): array
    {
        return [
            'sku' => 'SKU-0001',
            'stock_quantity' => 10,
        ];
    }

    public function test_an_active_seller_can_create_a_product_with_variants(): void
    {
        $seller = Seller::factory()->active()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($seller->user)
            ->postJson('/api/v1/seller/products', [
                'category_id' => $category->id,
                'title' => 'Bluetooth Speaker',
                'description' => 'Loud and portable.',
                'base_price' => '29.99',
                'variants' => [
                    $this->variantPayload(),
                    ['sku' => 'SKU-0002', 'price_override' => '34.99', 'stock_quantity' => 5],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonCount(2, 'data.variants');

        $this->assertDatabaseHas('products', ['title' => 'Bluetooth Speaker', 'store_id' => $seller->store->id]);
        $this->assertDatabaseHas('product_variants', ['sku' => 'SKU-0001', 'stock_quantity' => 10]);

        // TDD §3.2 module 13: the initial price is logged too.
        $product = $seller->store->products()->firstOrFail();
        $this->assertDatabaseHas('price_history', [
            'product_id' => $product->id,
            'old_price' => null,
            'new_price' => '29.99',
        ]);

        // TDD §6.4 rule 2: initial stock is written through the ledger.
        $this->assertDatabaseHas('inventory_ledger', ['delta' => 10, 'reason_code' => 'initial_stock']);
        $this->assertSame(15, $product->fresh()->stock_quantity);
    }

    public function test_a_non_active_seller_cannot_create_a_product(): void
    {
        $seller = Seller::factory()->create(['status' => 'pending']);
        $category = Category::factory()->create();

        $this->actingAs($seller->user)
            ->postJson('/api/v1/seller/products', [
                'category_id' => $category->id,
                'title' => 'Bluetooth Speaker',
                'base_price' => '29.99',
                'variants' => [$this->variantPayload()],
            ])
            ->assertForbidden();
    }

    public function test_a_buyer_cannot_create_a_product(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $category = Category::factory()->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/seller/products', [
                'category_id' => $category->id,
                'title' => 'Bluetooth Speaker',
                'base_price' => '29.99',
                'variants' => [$this->variantPayload()],
            ])
            ->assertForbidden();
    }

    public function test_a_product_cannot_be_assigned_to_a_non_leaf_category(): void
    {
        $seller = Seller::factory()->active()->create();
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id, 'depth' => 1]);

        $this->actingAs($seller->user)
            ->postJson('/api/v1/seller/products', [
                'category_id' => $parent->id,
                'title' => 'Bluetooth Speaker',
                'base_price' => '29.99',
                'variants' => [$this->variantPayload()],
            ])
            ->assertUnprocessable();
    }

    public function test_variant_skus_must_be_unique(): void
    {
        $seller = Seller::factory()->active()->create();
        $category = Category::factory()->create();

        $this->actingAs($seller->user)
            ->postJson('/api/v1/seller/products', [
                'category_id' => $category->id,
                'title' => 'Bluetooth Speaker',
                'base_price' => '29.99',
                'variants' => [
                    ['sku' => 'SKU-DUPLICATE', 'stock_quantity' => 1],
                    ['sku' => 'SKU-DUPLICATE', 'stock_quantity' => 1],
                ],
            ])
            ->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile app groundwork: App\Services\WishlistService's toggle/list
 * logic had no JSON-API surface before this — only the web dashboard
 * page and the storefront's Livewire heart button used it.
 */
class WishlistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggling_a_product_adds_it_then_removes_it(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        $this->actingAs($buyer)
            ->postJson("/api/v1/wishlist/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.wishlisted', true);

        $this->assertDatabaseHas('wishlist_items', ['user_id' => $buyer->id, 'product_id' => $product->id]);

        $this->actingAs($buyer)
            ->postJson("/api/v1/wishlist/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.wishlisted', false);

        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $buyer->id, 'product_id' => $product->id]);
    }

    public function test_the_index_lists_only_the_current_buyers_wishlist(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        WishlistItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'created_at' => now()]);

        $otherBuyer = User::factory()->withRole('buyer')->create();
        $otherProduct = Product::factory()->for($seller->store)->published()->create();
        WishlistItem::create(['user_id' => $otherBuyer->id, 'product_id' => $otherProduct->id, 'created_at' => now()]);

        $response = $this->actingAs($buyer)->getJson('/api/v1/wishlist')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($product->id, $response->json('data.0.product_id'));
    }

    public function test_a_guest_cannot_toggle_or_list_a_wishlist(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        $this->postJson("/api/v1/wishlist/{$product->id}")->assertUnauthorized();
        $this->getJson('/api/v1/wishlist')->assertUnauthorized();
    }
}

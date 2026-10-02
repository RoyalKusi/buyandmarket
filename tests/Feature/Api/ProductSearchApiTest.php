<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile app groundwork: the web storefront's browse/search/category
 * pages all go through App\Contracts\SearchProvider via one shared
 * Livewire component — this is that same contract's first JSON-API
 * surface, needed for the mobile home/search/category screens. There
 * was previously no product listing endpoint at all, only a
 * single-product show() by ID.
 */
class ProductSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_published_products_from_active_sellers_only(): void
    {
        $seller = Seller::factory()->active()->create();
        $published = Product::factory()->for($seller->store)->published()->create();

        $suspendedSeller = Seller::factory()->withStore()->create(['status' => 'suspended']);
        Product::factory()->for($suspendedSeller->store)->published()->create();

        Product::factory()->for($seller->store)->create(['status' => 'draft']);

        $response = $this->getJson('/api/v1/products')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($published->id, $response->json('data.0.id'));
    }

    public function test_it_filters_by_search_query(): void
    {
        $seller = Seller::factory()->active()->create();
        $match = Product::factory()->for($seller->store)->published()->create(['title' => 'Wireless Bluetooth Headphones']);
        Product::factory()->for($seller->store)->published()->create(['title' => 'Kitchen Blender']);

        $response = $this->getJson('/api/v1/products?q=headphones')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($match->id, $response->json('data.0.id'));
    }

    public function test_it_filters_by_category(): void
    {
        $seller = Seller::factory()->active()->create();
        $electronics = Category::factory()->create();
        $fashion = Category::factory()->create();

        $match = Product::factory()->for($seller->store)->for($electronics)->published()->create();
        Product::factory()->for($seller->store)->for($fashion)->published()->create();

        $response = $this->getJson("/api/v1/products?category_id={$electronics->id}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($match->id, $response->json('data.0.id'));
    }

    public function test_it_accepts_every_sort_key_the_search_provider_recognises(): void
    {
        $seller = Seller::factory()->active()->create();
        Product::factory()->for($seller->store)->published()->create();

        foreach (['relevance', 'price_low_high', 'price_high_low', 'newest'] as $sort) {
            $this->getJson("/api/v1/products?sort={$sort}")->assertOk();
        }
    }

    public function test_pagination_metadata_is_present(): void
    {
        $seller = Seller::factory()->active()->create();
        Product::factory()->for($seller->store)->published()->count(3)->create();

        $response = $this->getJson('/api/v1/products')->assertOk();

        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
        $this->assertSame(3, $response->json('meta.total'));
    }
}

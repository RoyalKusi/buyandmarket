<?php

namespace Tests\Feature\Storefront;

use App\Livewire\ProductGrid;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_only_returns_published_products_from_active_sellers(): void
    {
        $activeSeller = Seller::factory()->active()->create();
        $suspendedSeller = Seller::factory()->withStore()->create(['status' => 'suspended']);

        $visible = Product::factory()->for($activeSeller->store)->published()->create(['title' => 'Visible Speaker']);
        Product::factory()->for($activeSeller->store)->create(['status' => 'draft', 'title' => 'Draft Speaker']);
        Product::factory()->for($suspendedSeller->store)->published()->create(['title' => 'Suspended Seller Speaker']);

        Livewire::test(ProductGrid::class)
            ->assertSee($visible->title)
            ->assertDontSee('Draft Speaker')
            ->assertDontSee('Suspended Seller Speaker');
    }

    public function test_search_filters_by_query_text(): void
    {
        $seller = Seller::factory()->active()->create();
        Product::factory()->for($seller->store)->published()->create(['title' => 'Bluetooth Speaker', 'description' => 'Loud sound']);
        Product::factory()->for($seller->store)->published()->create(['title' => 'Kitchen Blender', 'description' => 'Fast blending']);

        Livewire::test(ProductGrid::class, ['q' => 'Bluetooth'])
            ->assertSee('Bluetooth Speaker')
            ->assertDontSee('Kitchen Blender');
    }

    public function test_a_category_locked_grid_only_shows_that_categorys_products(): void
    {
        $seller = Seller::factory()->active()->create();
        $productInCategory = Product::factory()->for($seller->store)->published()->create(['title' => 'In Category']);
        $otherProduct = Product::factory()->for($seller->store)->published()->create(['title' => 'Other Category']);

        Livewire::test(ProductGrid::class, ['categoryId' => $productInCategory->category_id])
            ->assertSee('In Category')
            ->assertDontSee('Other Category');
    }

    public function test_a_zero_result_search_shows_the_empty_state(): void
    {
        Livewire::test(ProductGrid::class, ['q' => 'nonexistent-product-xyz'])
            ->assertSee('No products found');
    }

    public function test_price_filters_narrow_results(): void
    {
        $seller = Seller::factory()->active()->create();
        Product::factory()->for($seller->store)->published()->create(['title' => 'Cheap Item', 'base_price' => '5.00']);
        Product::factory()->for($seller->store)->published()->create(['title' => 'Expensive Item', 'base_price' => '500.00']);

        Livewire::test(ProductGrid::class)
            ->set('minPrice', '100')
            ->assertSee('Expensive Item')
            ->assertDontSee('Cheap Item');
    }

    public function test_the_price_slider_ceiling_matches_the_highest_published_price(): void
    {
        $seller = Seller::factory()->active()->create();
        Product::factory()->for($seller->store)->published()->create(['base_price' => '42.00']);
        Product::factory()->for($seller->store)->published()->create(['base_price' => '250.00']);
        Product::factory()->for($seller->store)->create(['base_price' => '9999.00', 'status' => 'draft']);

        Livewire::test(ProductGrid::class)->assertViewHas('priceCeiling', 250);
    }
}

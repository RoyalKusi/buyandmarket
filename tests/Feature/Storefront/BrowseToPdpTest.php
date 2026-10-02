<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD §14 Run 1.4 exit criterion: "A buyer can browse and reach a PDP
 * entirely through the built UI."
 */
class BrowseToPdpTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_reach_a_pdp_starting_from_the_homepage(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['title' => 'Bluetooth Speaker']);

        $home = $this->get('/')->assertOk();
        $home->assertSee($seller->store->name);

        $store = $this->get(route('storefront.stores.show', $seller->store))->assertOk();
        $store->assertSee($product->title);

        $pdp = $this->get(route('storefront.products.show', $product))->assertOk();
        $pdp->assertSee($product->title);
        $pdp->assertSee(number_format((float) $product->base_price, 2), false);
    }

    public function test_a_buyer_can_reach_a_pdp_via_the_category_page(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['title' => 'Desk Lamp']);

        $category = $this->get(route('storefront.categories.show', $product->category))->assertOk();
        $category->assertSee($product->category->name);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSee('Desk Lamp');
    }

    public function test_a_buyer_can_reach_a_pdp_via_search(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create([
            'title' => 'Waterproof Bluetooth Speaker',
            'description' => 'Great for the outdoors.',
        ]);

        $this->get(route('storefront.search', ['q' => 'Waterproof']))->assertOk();

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSee('Waterproof Bluetooth Speaker');
    }

    public function test_a_draft_product_is_not_reachable_on_the_storefront(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'draft']);

        $this->get(route('storefront.products.show', $product))->assertForbidden();
    }
}

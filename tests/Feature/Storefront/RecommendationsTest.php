<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD §6.1/module 40's related/recently-viewed/"picked for you" rails,
 * flagged deferred since Run 1.4 — deterministic (see
 * App\Services\RecommendationService's docblock), not AI-curated.
 */
class RecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pdp_shows_related_products_from_the_same_category(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $seller = Seller::factory()->active()->create();

        $product = Product::factory()->for($seller->store)->published()->create(['category_id' => $category->id, 'title' => 'Main Product']);
        $related = Product::factory()->for($seller->store)->published()->create(['category_id' => $category->id, 'title' => 'Related Product']);
        $unrelated = Product::factory()->for($seller->store)->published()->create(['category_id' => $otherCategory->id, 'title' => 'Unrelated Product']);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSeeText('Related Product')
            ->assertDontSeeText('Unrelated Product');
    }

    public function test_viewing_products_populates_the_recently_viewed_rail(): void
    {
        $seller = Seller::factory()->active()->create();
        $first = Product::factory()->for($seller->store)->published()->create(['title' => 'First Viewed']);
        $second = Product::factory()->for($seller->store)->published()->create(['title' => 'Second Viewed']);

        $this->get(route('storefront.products.show', $first));
        $this->get(route('storefront.products.show', $second));

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSeeText('First Viewed')
            ->assertSeeText('Second Viewed');
    }

    public function test_picked_for_you_surfaces_products_from_a_buyers_purchased_category(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $category = Category::factory()->create();

        $boughtProduct = Product::factory()->for($seller->store)->published()->create(['category_id' => $category->id]);
        $variant = ProductVariant::factory()->for($boughtProduct)->create();

        $order = Order::factory()->for($buyer)->create(['status' => 'completed']);
        $orderGroup = OrderGroup::factory()->for($order)->for($seller)->create();
        OrderItem::factory()->for($orderGroup)->for($variant, 'variant')->create();

        $otherFromSameCategory = Product::factory()->for($seller->store)->published()->create(['category_id' => $category->id, 'title' => 'Same Category Pick']);

        $this->actingAs($buyer)->get(route('storefront.home'))
            ->assertOk()
            ->assertSeeText('Same Category Pick')
            ->assertDontSeeText($boughtProduct->title);
    }

    public function test_a_guest_sees_the_newest_published_products_as_picked_for_you(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['title' => 'Newest Listing']);

        $this->get(route('storefront.home'))->assertOk()->assertSeeText('Newest Listing');
    }
}

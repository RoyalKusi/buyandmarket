<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\Storefront\AddToCartForm;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TDD module 41 (analytics event stream) + §5.6/module 41 seller sales
 * summaries and performance insights, flagged deferred since Run
 * 1.7/1.11 for lacking the event stream. Run 1.22 adds both.
 */
class SellerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function enableTwoFactor(User $user): void
    {
        $user->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
    }

    public function test_viewing_a_product_records_a_product_view_event(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        $this->get(route('storefront.products.show', $product));

        $this->assertDatabaseHas('analytics_events', [
            'event_type' => 'product_view',
            'subject_type' => Product::class,
            'subject_id' => $product->id,
        ]);
    }

    public function test_adding_to_cart_records_an_add_to_cart_event(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        Livewire::test(AddToCartForm::class, ['product' => $product])
            ->set('variantId', $variant->id)
            ->call('addToCart');

        $this->assertDatabaseHas('analytics_events', [
            'event_type' => 'add_to_cart',
            'subject_type' => Product::class,
            'subject_id' => $product->id,
        ]);
    }

    public function test_the_seller_overview_shows_views_and_conversion_for_their_products(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $product = Product::factory()->for($seller->store)->published()->create(['title' => 'Tracked Product']);

        app(AnalyticsService::class)->record('product_view', $product);
        app(AnalyticsService::class)->record('product_view', $product);
        app(AnalyticsService::class)->record('order_placed', $product, null, ['quantity' => 1, 'revenue' => '19.99']);

        $this->actingAs($seller->user)->get('/seller/dashboard')
            ->assertOk()
            ->assertSeeText('Tracked Product')
            ->assertSeeText('50%');
    }

    public function test_the_seller_overview_shows_a_sales_total_from_recorded_revenue(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $product = Product::factory()->for($seller->store)->published()->create();

        app(AnalyticsService::class)->record('order_placed', $product, null, ['quantity' => 1, 'revenue' => '25.00']);
        app(AnalyticsService::class)->record('order_placed', $product, null, ['quantity' => 1, 'revenue' => '15.00']);

        $this->actingAs($seller->user)->get('/seller/dashboard')
            ->assertOk()
            ->assertSeeText('$40.00');
    }

    public function test_another_sellers_events_never_appear_in_this_sellers_summary(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $otherSeller = Seller::factory()->active()->create();
        $otherProduct = Product::factory()->for($otherSeller->store)->published()->create(['title' => 'Someone Elses Product']);

        app(AnalyticsService::class)->record('order_placed', $otherProduct, null, ['quantity' => 1, 'revenue' => '999.00']);

        $this->actingAs($seller->user)->get('/seller/dashboard')
            ->assertOk()
            ->assertDontSeeText('Someone Elses Product')
            ->assertDontSeeText('$999.00');
    }
}

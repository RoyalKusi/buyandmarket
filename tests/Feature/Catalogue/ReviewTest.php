<?php

namespace Tests\Feature\Catalogue;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Seller;
use App\Models\User;
use App\Services\SellerBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD module 33, flagged deferred since Run 1.4 ("rating row and
 * reviews tab content") — verified-purchase-only reviews.
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function buyWithOrder(User $buyer, Product $product, string $orderStatus = 'completed'): Order
    {
        $order = Order::factory()->for($buyer)->create(['status' => $orderStatus]);
        $seller = $product->store->seller;
        $orderGroup = OrderGroup::factory()->for($order)->for($seller)->create();
        $variant = ProductVariant::factory()->for($product)->create();
        OrderItem::factory()->for($orderGroup)->for($variant, 'variant')->create();

        return $order;
    }

    public function test_a_buyer_who_bought_the_product_can_leave_a_review(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $this->buyWithOrder($buyer, $product);

        $this->actingAs($buyer)
            ->post(route('storefront.products.reviews.store', $product), [
                'rating' => 5,
                'title' => 'Great speaker',
                'body' => 'Loud and clear, exactly as described.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'user_id' => $buyer->id,
            'rating' => 5,
            'status' => 'published',
        ]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSeeText('Great speaker')
            ->assertSeeText('1 review');
    }

    public function test_a_buyer_who_never_bought_the_product_cannot_review_it(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        $this->actingAs($buyer)
            ->post(route('storefront.products.reviews.store', $product), [
                'rating' => 5,
                'body' => 'Never actually bought this.',
            ])
            ->assertForbidden();
    }

    public function test_a_buyer_cannot_review_the_same_product_twice(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $this->buyWithOrder($buyer, $product);

        $this->actingAs($buyer)->post(route('storefront.products.reviews.store', $product), [
            'rating' => 4, 'body' => 'First review.',
        ])->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('storefront.products.reviews.store', $product), ['rating' => 2, 'body' => 'Second attempt.'])
            ->assertForbidden();

        $this->assertSame(1, Review::where('product_id', $product->id)->count());
    }

    public function test_a_pending_order_does_not_qualify_as_a_verified_purchase(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $this->buyWithOrder($buyer, $product, orderStatus: 'pending');

        $this->actingAs($buyer)
            ->post(route('storefront.products.reviews.store', $product), ['rating' => 5, 'body' => 'x'])
            ->assertForbidden();
    }

    public function test_an_admin_can_remove_a_review(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $this->enableTwoFactor($admin);
        $review = Review::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.dashboard.reviews.remove', $review), ['reason_code' => 'abusive'])
            ->assertRedirect();

        $this->assertSame('removed', $review->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.removed', 'subject_id' => $review->id]);
    }

    public function test_a_buyer_cannot_remove_their_own_review(): void
    {
        $review = Review::factory()->create();

        $this->actingAs($review->user)
            ->post(route('admin.dashboard.reviews.remove', $review), ['reason_code' => 'abusive'])
            ->assertForbidden();
    }

    public function test_top_rated_badge_is_awarded_once_the_seller_crosses_the_threshold(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        Review::factory()->for($product)->count(20)->create(['rating' => 5]);

        app(SellerBadgeService::class)->recompute($seller);

        $this->assertTrue($seller->fresh()->hasBadge('top_rated'));
    }

    public function test_top_rated_badge_is_not_awarded_below_the_review_count_threshold(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        Review::factory()->for($product)->count(5)->create(['rating' => 5]);

        app(SellerBadgeService::class)->recompute($seller);

        $this->assertFalse($seller->fresh()->hasBadge('top_rated'));
    }

    private function enableTwoFactor(User $user): void
    {
        $user->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
    }
}

<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD §3.2 module 7: draft -> pending_review -> published -> archived.
 */
class ProductLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owning_seller_can_submit_a_draft_product_for_review(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'draft']);

        $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/products/{$product->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review');
    }

    public function test_a_different_sellers_product_is_not_reachable_at_all(): void
    {
        $seller = Seller::factory()->active()->create();
        $otherSeller = Seller::factory()->active()->create();
        $product = Product::factory()->for($otherSeller->store)->create(['status' => 'draft']);

        $response = $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/products/{$product->id}/submit");

        // TDD §8.5: a seller's own request surface cannot even confirm
        // another seller's product exists.
        $response->assertNotFound();
    }

    public function test_an_admin_can_approve_a_pending_review_product_and_it_is_audit_logged(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'pending_review']);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/products/{$product->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'product.approved',
            'subject_type' => Product::class,
            'subject_id' => $product->id,
        ]);
    }

    public function test_a_product_cannot_be_published_unless_its_seller_is_active(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $seller = Seller::factory()->withStore()->create(['status' => 'under_review']);
        $product = Product::factory()->for($seller->store)->create(['status' => 'pending_review']);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/products/{$product->id}/approve")
            ->assertUnprocessable();

        $this->assertSame('pending_review', $product->fresh()->status);
    }

    public function test_the_seller_cannot_approve_their_own_product(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'pending_review']);

        $this->actingAs($seller->user)
            ->postJson("/api/v1/admin/products/{$product->id}/approve")
            ->assertForbidden();
    }

    public function test_an_admin_rejecting_a_product_requires_a_reason_code_and_returns_it_to_draft(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'pending_review']);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/products/{$product->id}/reject", [])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/products/{$product->id}/reject", [
                'reason_code' => 'poor_image_quality',
                'note' => 'Please provide a plain white background image.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.rejected',
            'subject_id' => $product->id,
        ]);
    }

    public function test_the_owning_seller_can_archive_a_published_product(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'published']);

        $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/products/{$product->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_a_price_update_is_logged_to_price_history_and_audited(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['base_price' => '10.00']);

        $this->actingAs($seller->user)
            ->patchJson("/api/v1/seller/products/{$product->id}/price", ['base_price' => '12.50'])
            ->assertOk()
            ->assertJsonPath('data.base_price', '12.50');

        $this->assertDatabaseHas('price_history', [
            'product_id' => $product->id,
            'old_price' => '10.00',
            'new_price' => '12.50',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.price_updated',
            'subject_id' => $product->id,
        ]);
    }

    public function test_a_published_product_is_publicly_viewable(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'published']);

        $this->getJson("/api/v1/products/{$product->id}")->assertOk();
    }

    public function test_a_draft_product_is_not_publicly_viewable(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'draft']);

        $this->getJson("/api/v1/products/{$product->id}")->assertForbidden();
    }
}

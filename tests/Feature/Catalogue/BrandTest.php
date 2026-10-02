<?php

namespace Tests\Feature\Catalogue;

use App\Models\Brand;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD §3.2 module 11: seller-suggested brands enter as pending and are
 * admin-approved before appearing in filters.
 */
class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_seller_can_suggest_a_brand_and_it_enters_as_pending(): void
    {
        $seller = Seller::factory()->active()->create();

        $response = $this->actingAs($seller->user)
            ->postJson('/api/v1/seller/brands', ['name' => 'Acme', 'slug' => 'acme']);

        $response->assertCreated();
        $this->assertDatabaseHas('brands', [
            'name' => 'Acme',
            'status' => 'pending',
            'suggested_by_seller_id' => $seller->id,
        ]);
    }

    public function test_a_buyer_cannot_suggest_a_brand(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/seller/brands', ['name' => 'Acme', 'slug' => 'acme'])
            ->assertForbidden();
    }

    public function test_an_admin_can_approve_a_pending_brand_and_it_is_audit_logged(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $brand = Brand::factory()->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/brands/{$brand->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'brand.approved',
            'subject_type' => Brand::class,
            'subject_id' => $brand->id,
        ]);
    }

    public function test_a_seller_cannot_approve_their_own_suggested_brand(): void
    {
        $seller = Seller::factory()->active()->create();
        $brand = Brand::factory()->create([
            'status' => 'pending',
            'suggested_by_seller_id' => $seller->id,
        ]);

        $this->actingAs($seller->user)
            ->postJson("/api/v1/admin/brands/{$brand->id}/approve")
            ->assertForbidden();
    }
}

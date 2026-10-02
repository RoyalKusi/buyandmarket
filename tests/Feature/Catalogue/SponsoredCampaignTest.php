<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SponsoredCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD module 15 (sponsored placements), flagged deferred since Run 1.4.
 * Seller-submitted, admin-approved — same moderation shape as brand
 * suggestions.
 */
class SponsoredCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function enableTwoFactor(User $user): void
    {
        $user->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
    }

    public function test_a_seller_can_submit_a_campaign_for_their_own_published_product(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $product = Product::factory()->for($seller->store)->published()->create();

        $this->actingAs($seller->user)->post('/seller/dashboard/sponsored-campaigns', [
            'product_id' => $product->id,
            'daily_budget' => '10.00',
            'starts_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('sponsored_campaigns', [
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_seller_cannot_sponsor_an_unpublished_product(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $product = Product::factory()->for($seller->store)->create(['status' => 'draft']);

        $this->actingAs($seller->user)
            ->post('/seller/dashboard/sponsored-campaigns', [
                'product_id' => $product->id,
                'daily_budget' => '10.00',
                'starts_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseMissing('sponsored_campaigns', ['product_id' => $product->id]);
    }

    public function test_an_admin_can_approve_a_pending_campaign(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $this->enableTwoFactor($admin);
        $campaign = SponsoredCampaign::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/dashboard/sponsored-campaigns/{$campaign->id}/approve")
            ->assertRedirect();

        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sponsored_campaign.approved']);
    }

    public function test_an_approved_campaign_for_a_published_product_appears_on_the_homepage(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['title' => 'Sponsored Speaker']);
        SponsoredCampaign::factory()->for($seller)->for($product)->active()->create();

        $this->get('/')->assertOk()->assertSeeText('Sponsored')->assertSeeText('Sponsored Speaker');
    }

    public function test_a_seller_cannot_approve_their_own_campaign(): void
    {
        $seller = Seller::factory()->active()->create();
        $this->enableTwoFactor($seller->user);
        $campaign = SponsoredCampaign::factory()->for($seller)->create();

        $this->actingAs($seller->user)
            ->post("/admin/dashboard/sponsored-campaigns/{$campaign->id}/approve")
            ->assertForbidden();
    }
}

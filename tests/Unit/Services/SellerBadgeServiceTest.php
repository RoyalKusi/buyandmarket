<?php

namespace Tests\Unit\Services;

use App\Models\Seller;
use App\Services\SellerBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerBadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_kyc_approved_seller_earns_the_verified_badge(): void
    {
        $seller = Seller::factory()->create(['kyc_status' => 'approved']);

        app(SellerBadgeService::class)->recompute($seller);

        $this->assertTrue($seller->fresh()->hasBadge('verified'));
    }

    public function test_a_seller_created_within_90_days_earns_the_new_seller_badge(): void
    {
        $seller = Seller::factory()->create(['created_at' => now()->subDays(10)]);

        app(SellerBadgeService::class)->recompute($seller);

        $this->assertTrue($seller->fresh()->hasBadge('new_seller'));
    }

    public function test_a_badge_is_revoked_once_it_no_longer_applies(): void
    {
        $seller = Seller::factory()->create(['created_at' => now()->subDays(10)]);
        $service = app(SellerBadgeService::class);
        $service->recompute($seller);
        $this->assertTrue($seller->fresh()->hasBadge('new_seller'));

        $seller->forceFill(['created_at' => now()->subDays(200)])->save();
        $service->recompute($seller);

        $this->assertFalse($seller->fresh()->hasBadge('new_seller'));
    }
}

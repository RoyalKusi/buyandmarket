<?php

namespace Tests\Feature\Api;

use App\Models\DeliveryRateCard;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile app groundwork: the web checkout's delivery step resolves a
 * store's rate cards server-side to render as options — this is that
 * same lookup's JSON-API equivalent, so a mobile buyer can show real
 * delivery methods/fees before submitting a selection to
 * CheckoutController::setDelivery().
 */
class StoreDeliveryRateCardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_a_stores_enabled_rate_cards(): void
    {
        $seller = Seller::factory()->withStore()->create();
        $enabled = DeliveryRateCard::factory()->create(['seller_id' => $seller->id, 'enabled' => true]);
        DeliveryRateCard::factory()->create(['seller_id' => $seller->id, 'enabled' => false]);

        $response = $this->getJson("/api/v1/stores/{$seller->store->id}/delivery-rate-cards")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($enabled->id, $response->json('data.0.id'));
    }

    public function test_it_excludes_another_sellers_rate_cards(): void
    {
        $seller = Seller::factory()->withStore()->create();
        $otherSeller = Seller::factory()->withStore()->create();
        DeliveryRateCard::factory()->create(['seller_id' => $otherSeller->id, 'enabled' => true]);

        $response = $this->getJson("/api/v1/stores/{$seller->store->id}/delivery-rate-cards")->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_a_guest_can_view_rate_cards(): void
    {
        $seller = Seller::factory()->withStore()->create();
        DeliveryRateCard::factory()->create(['seller_id' => $seller->id, 'enabled' => true]);

        $this->getJson("/api/v1/stores/{$seller->store->id}/delivery-rate-cards")->assertOk();
    }
}

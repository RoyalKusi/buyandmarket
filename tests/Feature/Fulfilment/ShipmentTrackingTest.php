<?php

namespace Tests\Feature\Fulfilment;

use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Seller;
use App\Models\Shipper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TDD §14 Run 1.6 exit criterion: "An order can be assigned, tracked
 * through delivery, and marked delivered."
 */
class ShipmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedOrderGroup(Seller $seller): OrderGroup
    {
        $order = Order::factory()->for(User::factory()->withRole('buyer'))->create(['status' => 'confirmed']);

        return OrderGroup::factory()->for($order)->for($seller)->create(['status' => 'confirmed']);
    }

    public function test_seller_can_assign_a_shipment_to_their_own_order_group_using_a_rate_card(): void
    {
        $seller = Seller::factory()->active()->create();
        $zone = DeliveryZone::factory()->create();
        DeliveryRateCard::factory()->for($seller)->for($zone, 'zone')->create(['method' => 'standard']);
        $orderGroup = $this->confirmedOrderGroup($seller);

        $response = $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/order-groups/{$orderGroup->id}/shipment", [
                'zone_id' => $zone->id,
                'method' => 'standard',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'assigned');

        $this->assertDatabaseHas('order_group_shipments', [
            'order_group_id' => $orderGroup->id,
            'zone_id' => $zone->id,
            'method' => 'standard',
        ]);
        $this->assertSame('processing', $orderGroup->fresh()->status);
        $this->assertNotNull($response->json('data.id'));
    }

    public function test_assigning_a_shipment_without_a_matching_rate_card_is_rejected(): void
    {
        $seller = Seller::factory()->active()->create();
        $zone = DeliveryZone::factory()->create();
        $orderGroup = $this->confirmedOrderGroup($seller);

        $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/order-groups/{$orderGroup->id}/shipment", [
                'zone_id' => $zone->id,
                'method' => 'express',
            ])
            ->assertUnprocessable();
    }

    public function test_assigning_a_shipment_to_another_sellers_order_group_is_not_found(): void
    {
        $seller = Seller::factory()->active()->create();
        $otherSeller = Seller::factory()->active()->create();
        $zone = DeliveryZone::factory()->create();
        $orderGroup = $this->confirmedOrderGroup($otherSeller);

        $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/order-groups/{$orderGroup->id}/shipment", [
                'zone_id' => $zone->id,
                'method' => 'standard',
            ])
            ->assertNotFound();
    }

    public function test_a_shipper_can_log_events_through_to_delivery_and_the_order_group_completes(): void
    {
        Storage::fake('shipments');

        $seller = Seller::factory()->active()->create();
        $zone = DeliveryZone::factory()->create();
        DeliveryRateCard::factory()->for($seller)->for($zone, 'zone')->create(['method' => 'standard']);
        $orderGroup = $this->confirmedOrderGroup($seller);

        $shipperUser = User::factory()->withRole('shipper')->create();
        $shipper = Shipper::factory()->for($shipperUser)->create();

        $shipmentId = $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/order-groups/{$orderGroup->id}/shipment", [
                'zone_id' => $zone->id,
                'method' => 'standard',
                'shipper_id' => $shipper->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($shipperUser)
            ->postJson("/api/v1/shipper/shipments/{$shipmentId}/events", ['event_type' => 'picked_up'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'picked_up');

        $this->assertSame('shipped', $orderGroup->fresh()->status);

        $this->actingAs($shipperUser)
            ->postJson("/api/v1/shipper/shipments/{$shipmentId}/events", ['event_type' => 'in_transit'])
            ->assertCreated();

        $delivered = $this->actingAs($shipperUser)
            ->postJson("/api/v1/shipper/shipments/{$shipmentId}/events", [
                'event_type' => 'delivered',
                'photo' => UploadedFile::fake()->image('proof.jpg'),
                'signature' => UploadedFile::fake()->image('signature.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'delivered');

        $this->assertCount(4, $delivered->json('data.events'));
        $this->assertSame('completed', $orderGroup->fresh()->status);
        $this->assertNotNull($orderGroup->fresh()->shipment->delivered_at);
        $this->assertNotNull($orderGroup->fresh()->shipment->proof_photo_path);
    }

    public function test_a_shipper_cannot_log_events_for_a_shipment_not_assigned_to_them(): void
    {
        $seller = Seller::factory()->active()->create();
        $zone = DeliveryZone::factory()->create();
        DeliveryRateCard::factory()->for($seller)->for($zone, 'zone')->create(['method' => 'standard']);
        $orderGroup = $this->confirmedOrderGroup($seller);

        $ownerUser = User::factory()->withRole('shipper')->create();
        $owner = Shipper::factory()->for($ownerUser)->create();
        $intruderUser = User::factory()->withRole('shipper')->create();
        Shipper::factory()->for($intruderUser)->create();

        $shipmentId = $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/order-groups/{$orderGroup->id}/shipment", [
                'zone_id' => $zone->id,
                'method' => 'standard',
                'shipper_id' => $owner->id,
            ])
            ->json('data.id');

        $this->actingAs($intruderUser)
            ->postJson("/api/v1/shipper/shipments/{$shipmentId}/events", ['event_type' => 'picked_up'])
            ->assertForbidden();
    }

    public function test_a_shipper_can_claim_an_unassigned_pooled_shipment(): void
    {
        $seller = Seller::factory()->active()->create();
        $zone = DeliveryZone::factory()->create();
        DeliveryRateCard::factory()->for($seller)->for($zone, 'zone')->create(['method' => 'standard']);
        $orderGroup = $this->confirmedOrderGroup($seller);

        $shipmentId = $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/order-groups/{$orderGroup->id}/shipment", [
                'zone_id' => $zone->id,
                'method' => 'standard',
            ])
            ->json('data.id');

        $shipperUser = User::factory()->withRole('shipper')->create();
        $shipper = Shipper::factory()->for($shipperUser)->create();

        $this->actingAs($shipperUser)
            ->postJson("/api/v1/shipper/shipments/{$shipmentId}/claim")
            ->assertOk()
            ->assertJsonPath('data.shipper_id', $shipper->id);
    }

    public function test_buyer_can_view_shipment_tracking_on_their_order(): void
    {
        $seller = Seller::factory()->active()->create();
        $zone = DeliveryZone::factory()->create();
        DeliveryRateCard::factory()->for($seller)->for($zone, 'zone')->create(['method' => 'standard']);
        $orderGroup = $this->confirmedOrderGroup($seller);
        $buyer = $orderGroup->order->user;

        $this->actingAs($seller->user)
            ->postJson("/api/v1/seller/order-groups/{$orderGroup->id}/shipment", [
                'zone_id' => $zone->id,
                'method' => 'standard',
            ])
            ->assertCreated();

        $this->actingAs($buyer)
            ->getJson("/api/v1/orders/{$orderGroup->order_id}")
            ->assertOk()
            ->assertJsonPath('data.order_groups.0.shipment.status', 'assigned')
            ->assertJsonCount(1, 'data.order_groups.0.shipment.events');
    }
}

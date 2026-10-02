<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile app groundwork: the web dashboard's "my orders" page had no
 * API equivalent before this — only a single-order show() by ID
 * existed, with no way for a mobile client to list its own orders.
 */
class OrdersIndexApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_sees_only_their_own_orders_newest_first(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $older = Order::factory()->for($buyer)->create(['created_at' => now()->subDay()]);
        $newer = Order::factory()->for($buyer)->create(['created_at' => now()]);

        $otherBuyer = User::factory()->withRole('buyer')->create();
        Order::factory()->for($otherBuyer)->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/orders')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    public function test_a_guest_cannot_list_orders(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_a_real_bearer_token_client_lists_their_orders(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $order = Order::factory()->for($buyer)->create();
        $token = $buyer->createToken('mobile')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/orders')->assertOk();

        $this->assertSame($order->id, $response->json('data.0.id'));
    }
}

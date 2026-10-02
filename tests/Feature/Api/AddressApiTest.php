<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile app groundwork: the web dashboard's address book had no API
 * equivalent — a mobile buyer could pick an existing address by ID
 * during checkout (already covered elsewhere) but had no way to add
 * one in the first place.
 */
class AddressApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_add_an_address(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/addresses', [
            'label' => 'Home',
            'recipient_name' => 'Tendai Moyo',
            'phone' => '+263771234567',
            'province' => 'Harare',
            'city' => 'Harare',
            'area' => 'Avondale',
            'street_address' => '12 Samora Machel Ave',
        ])->assertCreated();

        $this->assertDatabaseHas('addresses', ['user_id' => $buyer->id, 'label' => 'Home']);
        $this->assertSame($buyer->id, $response->json('data.user_id'));
    }

    public function test_setting_a_new_address_as_default_unsets_the_previous_default(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $existing = Address::factory()->for($buyer)->create(['is_default' => true]);

        $this->actingAs($buyer)->postJson('/api/v1/addresses', [
            'label' => 'Work',
            'recipient_name' => 'Tendai Moyo',
            'phone' => '+263771234567',
            'province' => 'Harare',
            'city' => 'Harare',
            'street_address' => '5 Second Street',
            'is_default' => true,
        ])->assertCreated();

        $this->assertFalse($existing->fresh()->is_default);
    }

    public function test_a_buyer_can_list_their_own_addresses_only(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        Address::factory()->for($buyer)->create();

        $otherBuyer = User::factory()->withRole('buyer')->create();
        Address::factory()->for($otherBuyer)->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/addresses')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_a_buyer_can_delete_their_own_address_but_not_anothers(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $address = Address::factory()->for($buyer)->create();

        $otherBuyer = User::factory()->withRole('buyer')->create();
        $othersAddress = Address::factory()->for($otherBuyer)->create();

        $this->actingAs($buyer)->deleteJson("/api/v1/addresses/{$othersAddress->id}")->assertNotFound();
        $this->actingAs($buyer)->deleteJson("/api/v1/addresses/{$address->id}")->assertNoContent();

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
        $this->assertDatabaseHas('addresses', ['id' => $othersAddress->id]);
    }

    public function test_a_guest_cannot_manage_addresses(): void
    {
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
        $this->postJson('/api/v1/addresses', [])->assertUnauthorized();
    }
}

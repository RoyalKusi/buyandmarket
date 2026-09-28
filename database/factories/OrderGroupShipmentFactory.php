<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use App\Models\OrderGroup;
use App\Models\OrderGroupShipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderGroupShipment>
 */
class OrderGroupShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_group_id' => OrderGroup::factory(),
            'shipper_id' => null,
            'zone_id' => DeliveryZone::factory(),
            'method' => 'standard',
            'status' => 'assigned',
        ];
    }
}

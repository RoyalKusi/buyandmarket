<?php

namespace Database\Factories;

use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRateCard>
 */
class DeliveryRateCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'zone_id' => DeliveryZone::factory(),
            'method' => 'standard',
            'base_fee' => fake()->randomFloat(2, 2, 10),
            'free_threshold' => null,
            'eta_min_days' => 2,
            'eta_max_days' => 4,
            'pickup_address' => null,
            'enabled' => true,
        ];
    }
}

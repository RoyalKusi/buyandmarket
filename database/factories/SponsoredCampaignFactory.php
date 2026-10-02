<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SponsoredCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsoredCampaign>
 */
class SponsoredCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'product_id' => Product::factory(),
            'daily_budget' => fake()->randomFloat(2, 5, 50),
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => null,
            'status' => 'pending',
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }
}

<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withRole('seller'),
            'business_name' => fake()->company(),
            'status' => 'pending',
            'kyc_status' => 'pending',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'kyc_status' => 'approved',
        ])->afterCreating(function (Seller $seller) {
            // TDD §3.1 module 3: one store per seller. Run 1.3 owns the
            // actual store-creation onboarding step; an active seller in
            // tests is assumed to have already been through it.
            Store::factory()->for($seller)->create();
        });
    }
}

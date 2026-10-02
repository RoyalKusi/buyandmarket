<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Home',
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('07########'),
            'province' => 'Harare',
            'city' => 'Harare',
            'area' => 'Avondale',
            'street_address' => fake()->streetAddress(),
            'is_default' => true,
        ];
    }
}

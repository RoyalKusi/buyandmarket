<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'seller_id' => Seller::factory(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'name' => $name,
        ];
    }
}

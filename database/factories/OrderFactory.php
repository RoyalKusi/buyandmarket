<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'BM-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'total' => fake()->randomFloat(2, 10, 500),
            'status' => 'pending',
        ];
    }
}

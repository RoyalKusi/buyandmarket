<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderGroup>
 */
class OrderGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'seller_id' => Seller::factory(),
            'status' => 'pending',
            'subtotal' => fake()->randomFloat(2, 10, 300),
            'commission_amount' => 0,
            'delivery_fee' => 0,
        ];
    }
}

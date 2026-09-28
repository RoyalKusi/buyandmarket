<?php

namespace Database\Factories;

use App\Models\OrderGroup;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_group_id' => OrderGroup::factory(),
            'variant_id' => ProductVariant::factory(),
            'quantity' => 1,
            'price_at_purchase' => fake()->randomFloat(2, 5, 200),
        ];
    }
}

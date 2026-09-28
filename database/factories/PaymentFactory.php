<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'paynow',
            'provider_reference' => 'BM-'.strtoupper(Str::random(10)),
            'amount' => fake()->randomFloat(2, 10, 500),
            'status' => 'initiated',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'transaction_id' => null,
            'payment_type' => null,
            'gross_amount' => fake()->randomFloat(2, 10000, 1000000),
            'payment_status' => 'pending',
            'midtrans_response' => null,
            'snap_token' => fake()->uuid(),
            'paid_at' => null,
            'expired_at' => now()->addDay(),
        ];
    }

    public function settlement(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'settlement',
            'transaction_id' => fake()->uuid(),
            'paid_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'expire',
            'expired_at' => now()->subHour(),
        ]);
    }
}
<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = 'SO-' . now()->format('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'order_number' => $number,
            'customer_id' => Customer::factory(),
            'status' => $this->faker->randomElement(['pending_payment', 'paid', 'fulfilled', 'cancelled', 'refunded']),
            'currency' => 'USD',
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 0,
            'placed_at' => $this->faker->optional(0.8)->dateTimeBetween('-2 months', 'now'),
            'paid_at' => null,
            'cancelled_at' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}

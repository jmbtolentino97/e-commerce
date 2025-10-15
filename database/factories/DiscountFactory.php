<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Discount>
 */
class DiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['percentage', 'fixed', 'free_shipping']);
        return [
            'code' => $this->faker->boolean(70)
                        ? $this->faker->unique()->regexify('[A-Z0-9]{6,10}')
                        : null,
            'name' => $this->faker->sentence(3),
            'type' => $type,
            'target' => $this->faker->randomElement(['order', 'item']),
            'value' => $type === 'percentage'
                ? $this->faker->randomFloat(2, 5, 30)
                : ($type === 'fixed' ? $this->faker->randomFloat(2, 1, 200) : 0),
            'min_order_amount' => $this->faker->optional()->randomFloat(2, 20, 300),
            'max_discount_amount' => $this->faker->optional()->randomFloat(2, 20, 300),
            'usage_limit' => $this->faker->optional()->numberBetween(50, 500),
            'per_customer_limit' => $this->faker->optional()->numberBetween(1, 5),
            'stackable' => $this->faker->boolean(20),
            'active' => true,
            'starts_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'ends_at' => $this->faker->optional()->dateTimeBetween('now', '+2 months'),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => $this->faker->randomElement([
                'purchase', 'sale', 'return', 'adjustment', 'reservation', 'release'
            ]),
            'quantity' => $this->faker->numberBetween(-10, 20),
            'reference_type' => $this->faker->optional()->randomElement(['order', 'order_item']),
            'reference_id' => null,
            'note' => $this->faker->optional()->sentence(),
            'created_by' => null,
        ];
    }
}

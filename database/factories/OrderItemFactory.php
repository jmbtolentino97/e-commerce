<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->make();
        $unit = $this->faker->randomFloat(2, 5, 999);
        $qty = $this->faker->numberBetween(1, 5);
        $disc = $this->faker->randomFloat(2, 0, $unit * 0.3);
        $tax = $this->faker->randomFloat(2, 0, $unit * 0.15);
        return [
            'order_id' => Order::factory(),
            'product_id' => null, // link in seeder to existing product to keep snapshots sane
            'sku' => $product->sku ?? $this->faker->unique()->bothify('SKU-####??'),
            'name' => $product->name ?? $this->faker->words(3, true),
            'unit_price' => $unit,
            'quantity' => $qty,
            'discount_amount' => $disc,
            'tax_amount' => $tax,
            'total' => round(($unit * $qty) - $disc + $tax, 2),
        ];
    }
}

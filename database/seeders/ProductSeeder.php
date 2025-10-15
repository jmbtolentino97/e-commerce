<?php

namespace Database\Seeders;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::factory()->count(40)->create();

        $products->each(function (Product $product) {
            if ($product->track_inventory) {
                $qty = random_int(10, 100);
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'type' => 'purchase',
                    'quantity' => $qty,
                    'reference_type' => null,
                    'reference_id' => null,
                    'note' => 'Initial stock seeding',
                    'created_by' => null,
                ]);
            }
        });
    }
}

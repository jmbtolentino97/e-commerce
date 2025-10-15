<?php

namespace Database\Seeders;

use App\Models\Discount;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Discount::factory()->create([
            'code' => 'WELCOME10',
            'type' => 'percentage',
            'target' => 'order',
            'value' => 10,
            'active' => true,
        ]);

        Discount::factory()->create([
            'code' => 'FREESHIP',
            'type' => 'free_shipping',
            'target' => 'order',
            'value' => 0,
            'active' => true,
        ]);

        Discount::factory()->count(5)->create();
    }
}

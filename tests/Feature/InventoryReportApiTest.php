<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('stock list returns paginated rows with filters and sort', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => Hash::make('password'),
    ]);
    $token = auth()->login($admin);

    // Two products with different stock
    $p1 = Product::factory()->create(['sku' => 'SKU-A', 'name' => 'Alpha Shoe']);
    $p2 = Product::factory()->create(['sku' => 'SKU-Z', 'name' => 'Zulu Boot']);

    // Seed movements
    DB::table('inventory_movements')->insert([
        ['product_id' => $p1->id, 'type' => 'purchase', 'quantity' => 10, 'created_at' => now(), 'updated_at' => now()],
        ['product_id' => $p2->id, 'type' => 'purchase', 'quantity' => 5,  'created_at' => now(), 'updated_at' => now()],
        ['product_id' => $p2->id, 'type' => 'sale',     'quantity' => -2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $res = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->getJson('/api/inventory/stock?filter[name]=shoe&sort=-stock_on_hand');

    $res->assertStatus(200)->assertJsonStructure(['data', 'meta']);

    $data = collect($res->json('data'));
    // Only Alpha Shoe matches "shoe"
    expect($data->count())->toBe(1);
    expect($data->first()['sku'])->toBe('SKU-A');
    expect((int) $data->first()['stock_on_hand'])->toBe(10);
});

test('single product stock endpoint shows stock_on_hand', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => Hash::make('password'),
    ]);
    $token = auth()->login($admin);

    $p = Product::factory()->create(['sku' => 'SKU-ONE', 'name' => 'One Thing']);

    InventoryMovement::create(['product_id' => $p->id, 'type' => 'purchase', 'quantity' => 7]);
    InventoryMovement::create(['product_id' => $p->id, 'type' => 'sale',     'quantity' => -3]);

    $res = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->getJson('/api/inventory/stock/' . $p->id);

    $res->assertStatus(200)
        ->assertJsonPath('data.product_id', $p->id)
        ->assertJsonPath('data.stock_on_hand', 4);
});

test('movement history can filter by product, type, and date range', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => Hash::make('password'),
    ]);
    $token = auth()->login($admin);

    $p = Product::factory()->create();

    InventoryMovement::create(['product_id' => $p->id, 'type' => 'purchase', 'quantity' => 5,  'created_at' => now()->subDays(10)]);
    InventoryMovement::create(['product_id' => $p->id, 'type' => 'sale',     'quantity' => -2, 'created_at' => now()->subDays(5)]);
    InventoryMovement::create(['product_id' => $p->id, 'type' => 'sale',     'quantity' => -1, 'created_at' => now()->subDays(1)]);

    $res = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->getJson('/api/inventory/movements?filter[product_id]=' . $p->id . '&filter[type]=sale&filter[min_created_at]=' . now()->subDays(6)->toDateString() . '&filter[max_created_at]=' . now()->toDateString());

    $res->assertStatus(200);

    $data = collect($res->json('data'));
    // Should include the last two 'sale' entries only
    expect($data->count())->toBe(2);
});

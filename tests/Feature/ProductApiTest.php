<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('can list products with filters, sorts, includes', function () {
    $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
    $token = auth()->login($admin);

    Product::factory()->create(['sku' => 'SKU-ABC1', 'name' => 'Alpha', 'price' => 10]);
    Product::factory()->create(['sku' => 'SKU-XYZ9', 'name' => 'Zulu', 'price' => 50]);

    $res = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->getJson('/api/products?filter[name]=Al&sort=-price');

    $res->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);

    expect(collect($res->json('data'))->count())->toBe(1);
});

test('admin can create update delete product', function () {
    $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
    $token = auth()->login($admin);

    // Create
    $create = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/products', [
        'sku' => 'SKU-111AA',
        'name' => 'Test Product',
        'price' => 12.34,
        'is_active' => true,
        'track_inventory' => true,
    ]);

    $create->assertStatus(201);
    $id = $create->json('data.id');

    $update = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->putJson('/api/products/' . $id, [
        'name' => 'Updated Product',
        'price' => 15.00,
    ]);

    $update->assertStatus(200)->assertJsonPath('data.name', 'Updated Product');

    $del = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->deleteJson('/api/products/' . $id);

    $del->assertStatus(200);
});

test('staff cannot create product', function () {
    $staff = User::factory()->create(['role' => 'staff', 'password' => Hash::make('password')]);
    $token = auth()->login($staff);

    $res = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/products', [
        'sku' => 'SKU-222BB',
        'name' => 'Nope Product',
        'price' => 9.99,
    ]);

    $res->assertStatus(403);
});

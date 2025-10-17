<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('can create draft order, add items, place, pay, fulfill with inventory movements', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => Hash::make('password'),
    ]);
    $token = auth()->login($admin);

    $customer = Customer::factory()->create();

    $p1 = Product::factory()->create(['price' => 100, 'track_inventory' => true]);
    $p2 = Product::factory()->create(['price' => 50, 'track_inventory' => true]);

    // create draft
    $create = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'shipping_total' => 20,
    ]);

    $create->assertStatus(201);
    $orderId = $create->json('data.id');

    // add two items
    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$orderId}/items", [
        'product_id' => $p1->id,
        'quantity' => 2,
        'tax_rate' => 0.12,
    ])->assertStatus(201);

    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$orderId}/items", [
        'product_id' => $p2->id,
        'quantity' => 1,
        'tax_rate' => 0.12,
    ])->assertStatus(201);

    // place (creates reservation movements)
    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$orderId}/place")->assertStatus(200);

    $reservations = InventoryMovement::query()->where('type', 'reservation')->count();
    expect($reservations)->toBe(2);

    // pay
    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$orderId}/pay")->assertStatus(200);

    // fulfill (release + sale)
    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$orderId}/fulfill")->assertStatus(200);

    $release = InventoryMovement::query()->where('type', 'release')->count();
    $sales = InventoryMovement::query()->where('type', 'sale')->count();

    expect($release)->toBeGreaterThanOrEqual(2);
    expect($sales)->toBe(2);
});

test('cannot modify non-draft order items', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => Hash::make('password'),
    ]);
    $token = auth()->login($admin);

    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['price' => 10]);

    // create draft
    $orderRes = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/orders', [
        'customer_id' => $customer->id,
    ]);

    $orderId = $orderRes->json('data.id');

    // add item
    $itemRes = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$orderId}/items", [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertStatus(201);

    // place order
    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$orderId}/place")->assertStatus(200);

    // attempt to add another item after draft -> 422
    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$orderId}/items", [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertStatus(422);
});

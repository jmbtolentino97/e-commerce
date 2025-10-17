<?php

use App\Models\Customer;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can CRUD discounts and filter/sort', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => Hash::make('password'),
    ]);
    $token = auth()->login($admin);

    // Create
    $create = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/discounts', [
        'code' => 'SAVE10',
        'name' => 'Save Ten',
        'type' => 'percentage',
        'target' => 'order',
        'value' => 10,
        'active' => true,
    ]);

    $create->assertStatus(201);
    $id = $create->json('data.id');

    // List with filter
    $list = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->getJson('/api/discounts?filter[code]=SAVE&sort=created_at');

    $list->assertStatus(200)->assertJsonStructure(['data', 'meta']);

    // Update
    $upd = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->putJson('/api/discounts/' . $id, [
        'name' => 'Save Ten Updated',
    ]);

    $upd->assertStatus(200)->assertJsonPath('data.name', 'Save Ten Updated');

    // Delete
    $del = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->deleteJson('/api/discounts/' . $id);

    $del->assertStatus(200);
});

test('non-stackable discount blocks when another exists', function () {
    $admin = User::factory()->create([
        'role' => 'admin', 'password' => Hash::make('password'),
    ]);
    $token = auth()->login($admin);

    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['price' => 100]);

    // order + item
    $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'draft']);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'unit_price' => 100,
        'quantity' => 1,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    // First discount applied at order-level
    Discount::factory()->create([
        'code' => 'FIRST10',
        'type' => 'percentage',
        'target' => 'order',
        'min_order_amount' => null,
        'value' => 10,
        'stackable' => false,
        'active' => true,
    ]);

    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$order->id}/apply-discount", [
        'code' => 'FIRST10',
        'scope' => 'order',
    ])->assertStatus(200);

    // Second non-stackable should fail
    Discount::factory()->create([
        'code' => 'SECOND5',
        'type' => 'percentage',
        'target' => 'order',
        'value' => 5,
        'stackable' => false,
        'active' => true,
    ]);

    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$order->id}/apply-discount", [
        'code' => 'SECOND5',
        'scope' => 'order',
    ])->assertStatus(422);
});

test('usage limits and per-customer limits are enforced', function () {
    $admin = User::factory()->create([
        'role' => 'admin', 'password' => Hash::make('password'),
    ]);
    $token = auth()->login($admin);

    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['price' => 50]);

    // create discount with strict limits
    $d = Discount::factory()->create([
        'code' => 'ONCEONLY',
        'type' => 'percentage',
        'target' => 'order',
        'value' => 10,
        'min_order_amount' => null,
        'usage_limit' => 1,
        'per_customer_limit' => 1,
        'active' => true,
    ]);

    // first order
    $o1 = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'draft']);
    OrderItem::create([
        'order_id' => $o1->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'unit_price' => 50,
        'quantity' => 1,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total' => 50,
    ]);

    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$o1->id}/apply-discount", [
        'code' => 'ONCEONLY',
        'scope' => 'order',
    ])->assertStatus(200);

    // second order attempt by same customer → should fail due to per_customer_limit + usage_limit
    $o2 = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'draft']);

    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson("/api/orders/{$o2->id}/apply-discount", [
        'code' => 'ONCEONLY',
        'scope' => 'order',
    ])->assertStatus(422);
});

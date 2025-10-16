<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('can list customers with filters and sorts', function () {
    $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
    $token = auth()->login($admin);

    Customer::factory()->create(['first_name' => 'Jane', 'last_name' => 'Alpha', 'email' => 'a@example.test']);
    Customer::factory()->create(['first_name' => 'John', 'last_name' => 'Zulu', 'email' => 'z@example.test']);

    $res = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->getJson('/api/customers?filter[last_name]=Al&sort=created_at');

    $res->assertStatus(200);
    expect(collect($res->json('data'))->count())->toBe(1);
});

test('admin can create update delete customer', function () {
    $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
    $token = auth()->login($admin);

    $create = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/customers', [
        'first_name' => 'Tony',
        'last_name' => 'Stark',
        'email' => 'tony@example.com',
        'password' => 'secret1234',
    ]);

    $create->assertStatus(201);
    $id = $create->json('data.id');

    $update = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->putJson('/api/customers/' . $id, [
        'phone' => '+639123456789',
    ]);

    $update->assertStatus(200)->assertJsonPath('data.phone', '+639123456789');

    $del = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->deleteJson('/api/customers/' . $id);

    $del->assertStatus(200);
});

test('staff cannot create customer', function () {
    $staff = User::factory()->create(['role' => 'staff', 'password' => Hash::make('password')]);
    $token = auth()->login($staff);

    $res = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/customers', [
        'first_name' => 'Bruce',
        'last_name' => 'Wayne',
        'email' => 'bruce@example.com',
        'password' => 'secret1234',
    ]);

    $res->assertStatus(403);
});

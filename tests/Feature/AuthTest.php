<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('can login and get jwt token', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token_type', 'expires_in']);

    $token = $response->json('token');

    expect($token)->not->toBeNull();
});

test('me endpoint returns current user', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $token = auth()->login($user);

    $response = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $user->id);
});

test('refresh returns a new token', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $token = auth()->login($user);

    $response = $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/auth/refresh');

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'token_type', 'expires_in']);

    $new = $response->json('token');

    expect($new)->not->toBe($token);
});

test('logout invalidates the token', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $token = auth()->login($user);

    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->postJson('/api/auth/logout')
        ->assertStatus(200);

    $this->withHeaders([
        'Authorization'=> 'Bearer ' . $token,
        'Accept'=> 'application/json',
    ])->getJson('/api/auth/me')
        ->assertStatus(401);
});

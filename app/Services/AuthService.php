<?php

namespace App\Services;

use App\Models\User;

class AuthService
{
    public function register(array $data): array {
        $user = User::create($data);
        $token = auth()->login($user);
        return [$user, $token];
    }

    public function login(string $email, string $password): ?string {
        if (!$token = auth()->attempt(['email' => $email, 'password' => $password])) return null;

        return $token;
    }

    public function logout(): void { auth()->logout(); }

    public function refresh(): string { return auth()->refresh(); }

    public function me(): User { return auth()->user(); }
}

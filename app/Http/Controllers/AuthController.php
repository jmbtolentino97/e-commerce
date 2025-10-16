<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use App\Http\Resources\UserResource;
use App\Http\Requests\Auth\LoginRequest;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, AuthService $service)
    {
        [$user, $token] = $service->register($request->validated());
        return response()->json([
            'message' => 'User registered successfully.',
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ], 201);
    }

    public function login(LoginRequest $request, AuthService $service)
    {
        $token = $service->login($request->email, $request->password);
        if (!$token) return response()->json(['message' => 'Invalid credentials.'], 401);

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ]);
    }

    public function me(AuthService $service)
    {
        return new UserResource($service->me());
    }

    public function logout(AuthService $service)
    {
        $service->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh(AuthService $service)
    {
        $token = $service->refresh();
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ]);
    }
}

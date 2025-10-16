<?php

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [\App\Http\Controllers\AuthController::class, 'register']);
    Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);

    Route::middleware('jwt.auth')->group(function () {
        Route::get('me', [\App\Http\Controllers\AuthController::class, 'me']);
        Route::post('refresh', [\App\Http\Controllers\AuthController::class, 'refresh']);
        Route::post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
    });
});

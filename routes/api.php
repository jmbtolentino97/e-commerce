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

Route::middleware('jwt.auth')->group(function() {
    Route::apiResource('products', \App\Http\Controllers\ProductController::class);
    Route::apiResource('customers', \App\Http\Controllers\CustomerController::class);
    Route::apiResource('discounts', \App\Http\Controllers\DiscountController::class);

    Route::prefix('orders')->group(function () {
        Route::get('', [\App\Http\Controllers\OrderController::class, 'index']);
        Route::get('{order}', [\App\Http\Controllers\OrderController::class, 'show']);

        // draft lifecycle
        Route::post('', [\App\Http\Controllers\OrderController::class, 'store']); // create draft
        Route::post('{order}/place', [\App\Http\Controllers\OrderController::class, 'place']);
        Route::post('{order}/pay', [\App\Http\Controllers\OrderController::class, 'pay']);
        Route::post('{order}/fulfill', [\App\Http\Controllers\OrderController::class, 'fulfill']);
        Route::post('{order}/cancel', [\App\Http\Controllers\OrderController::class, 'cancel']);

        // items
        Route::post('{order}/items', [\App\Http\Controllers\OrderController::class, 'addItem']);
        Route::put('{order}/items/{item}', [\App\Http\Controllers\OrderController::class, 'updateItem']);
        Route::delete('{order}/items/{item}', [\App\Http\Controllers\OrderController::class, 'removeItem']);

        // discounts
        Route::post('{order}/apply-discount', [\App\Http\Controllers\OrderController::class, 'applyDiscount']);
        Route::delete('{order}/remove-discounts', [\App\Http\Controllers\OrderController::class, 'removeDiscounts']);
    });

    Route::prefix('inventory')->group(function () {
        // Stock on hand
        Route::get('stock', [\App\Http\Controllers\InventoryReportController::class, 'stock']);
        Route::get('stock/{product}', [\App\Http\Controllers\InventoryReportController::class, 'stockOf']);

        Route::get('movements', [\App\Http\Controllers\InventoryReportController::class, 'movements']);
    });
});

<?php

use App\Http\Controllers\AddonServiceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PrintPriceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mayoka POS
|--------------------------------------------------------------------------
*/

// Auth (public)
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:web')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Shifts (all authenticated users)
    Route::get('/shifts/active', [ShiftController::class, 'active']);
    Route::post('/shifts/open', [ShiftController::class, 'open']);
    Route::put('/shifts/{shift}/close', [ShiftController::class, 'close']);

    // Product search (kasir + owner)
    Route::get('/products/search', [ProductController::class, 'search']);

    // Print price calculate (kasir + owner)
    Route::get('/print-prices/calculate', [PrintPriceController::class, 'calculate']);
    Route::get('/print-prices', [PrintPriceController::class, 'index']);

    // Addon services list (kasir + owner)
    Route::get('/addon-services', [AddonServiceController::class, 'index']);

    // Owner-only routes
    Route::middleware('role:owner')->group(function () {

        // User management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Shift history
        Route::get('/shifts', [ShiftController::class, 'index']);

        // Categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        // Products
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/products/{product}/stock-adjust', [ProductController::class, 'stockAdjust']);

        // Print prices
        Route::post('/print-prices', [PrintPriceController::class, 'store']);
        Route::put('/print-prices/{printPrice}', [PrintPriceController::class, 'update']);
        Route::delete('/print-prices/{printPrice}', [PrintPriceController::class, 'destroy']);

        // Print price tiers
        Route::post('/print-prices/{printPrice}/tiers', [PrintPriceController::class, 'storeTier']);
        Route::put('/print-price-tiers/{tier}', [PrintPriceController::class, 'updateTier']);
        Route::delete('/print-price-tiers/{tier}', [PrintPriceController::class, 'destroyTier']);

        // Addon services
        Route::post('/addon-services', [AddonServiceController::class, 'store']);
        Route::put('/addon-services/{addonService}', [AddonServiceController::class, 'update']);
        Route::delete('/addon-services/{addonService}', [AddonServiceController::class, 'destroy']);
    });
});

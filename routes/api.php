<?php

use App\Http\Controllers\AuthController;
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

    // Owner-only routes
    Route::middleware('role:owner')->group(function () {

        // User management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Shift history
        Route::get('/shifts', [ShiftController::class, 'index']);
    });
});

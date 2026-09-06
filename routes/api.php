<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// The SPA uses cookie-based (stateful) authentication, so every API route runs
// through the "web" middleware group to guarantee session + CSRF support.
Route::middleware('web')->group(function () {
    // --- Authentication ---
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Dashboard aggregation.
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Core resources available to both Admin and Manager.
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('bills', BillController::class);
        Route::patch('bills/{bill}/pay', [BillController::class, 'markPaid']);

        // User management — Admin only.
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('users', UserController::class);
        });
    });
});

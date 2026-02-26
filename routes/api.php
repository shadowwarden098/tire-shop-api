<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AiController;


// ─── AUTH (públicas) ───────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ─── RUTAS PÚBLICAS (empleados sin login) ─────────────────────────────────
Route::apiResource('products',       ProductController::class);
Route::post('products/{id}/update-stock', [ProductController::class, 'updateStock']);
Route::get('products/low-stock',     [ProductController::class, 'lowStock']);
Route::get('products/stats',         [ProductController::class, 'stats']);

Route::apiResource('customers',      CustomerController::class);
Route::apiResource('vehicles',       VehicleController::class);
Route::apiResource('services',       ServiceController::class);

Route::get('service-records',        [ServiceController::class, 'indexRecords']);
Route::post('service-records',       [ServiceController::class, 'storeRecord']);
Route::get('service-records/{id}',   [ServiceController::class, 'showRecord']);
Route::put('service-records/{id}',   [ServiceController::class, 'updateRecord']);
Route::delete('service-records/{id}',[ServiceController::class, 'destroyRecord']);

Route::get('sales',                  [SaleController::class, 'index']);
Route::post('sales',                 [SaleController::class, 'store']);
Route::get('sales/stats',            [SaleController::class, 'stats']);
Route::get('sales/{id}',             [SaleController::class, 'show']);
Route::post('sales/{id}/cancel',     [SaleController::class, 'cancel']);

Route::get('exchange-rate/current',  [ExchangeRateController::class, 'current']);
Route::get('exchange-rate/history',  [ExchangeRateController::class, 'history']);
Route::get('exchange-rate/convert',  [ExchangeRateController::class, 'convert']);

Route::get('reports/dashboard',      [ReportController::class, 'dashboard']);

// ─── RUTAS SOLO ADMIN (requieren token) ───────────────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/logout',           [AuthController::class, 'logout']);
    Route::get('auth/me',                [AuthController::class, 'me']);

    Route::get('reports/financial',      [ReportController::class, 'financial']);
    Route::get('reports/inventory',      [ReportController::class, 'inventory']);

    Route::apiResource('expenses',       ExpenseController::class);
    Route::get('expenses/summary',       [ExpenseController::class, 'summary']);
    Route::get('expenses/categories',    [ExpenseController::class, 'categories']);

    Route::post('exchange-rate/update-api',    [ExchangeRateController::class, 'updateFromApi']);
    Route::post('exchange-rate/update-manual', [ExchangeRateController::class, 'updateManual']);

    Route::prefix('admin')->group(function () {
        Route::get('users',              [AuthController::class, 'index']);
        Route::post('users',             [AuthController::class, 'store']);
        Route::put('users/{id}',         [AuthController::class, 'update']);
        Route::delete('users/{id}',      [AuthController::class, 'destroy']);
    });

  // Ruta pública de IA
Route::post('ai/chat', [AiController::class, 'chat']);
});
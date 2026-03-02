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

// ─── IA ───────────────────────────────────────────────────────────────────
Route::post('ai/chat',    [AiController::class, 'chat']);
Route::get('ai/alerts',   [AiController::class, 'alerts']);
Route::get('ai/history',  [AiController::class, 'history']);
Route::delete('ai/history', [AiController::class, 'clearHistory']);

// ─── PRODUCTOS — rutas específicas ANTES del apiResource ──────────────────
Route::get('products/low-stock',          [ProductController::class, 'lowStock']);
Route::get('products/stats',              [ProductController::class, 'stats']);
Route::post('products/{id}/update-stock', [ProductController::class, 'updateStock']);
Route::apiResource('products', ProductController::class);

// ─── CLIENTES / VEHÍCULOS ─────────────────────────────────────────────────
Route::apiResource('customers', CustomerController::class);
Route::apiResource('vehicles',  VehicleController::class);

// ─── SERVICIOS ────────────────────────────────────────────────────────────
Route::apiResource('services', ServiceController::class);

// ─── REGISTROS DE SERVICIOS ───────────────────────────────────────────────
Route::get('service-records',      [ServiceController::class, 'records']);
Route::post('service-records',     [ServiceController::class, 'storeRecord']);
Route::get('service-records/{id}', [ServiceController::class, 'showRecord']);

// ─── VENTAS ───────────────────────────────────────────────────────────────
Route::get('sales/stats',         [SaleController::class, 'stats']);
Route::get('sales',               [SaleController::class, 'index']);
Route::post('sales',              [SaleController::class, 'store']);
Route::get('sales/{id}',          [SaleController::class, 'show']);
Route::post('sales/{id}/cancel',  [SaleController::class, 'cancel']);

// ─── TIPO DE CAMBIO (públicas) ────────────────────────────────────────────
Route::get('exchange-rate/current',  [ExchangeRateController::class, 'current']);
Route::get('exchange-rate/history',  [ExchangeRateController::class, 'history']);
Route::get('exchange-rate/convert',  [ExchangeRateController::class, 'convert']);

// ─── DASHBOARD ────────────────────────────────────────────────────────────
Route::get('reports/dashboard',       [ReportController::class, 'dashboard']);
Route::get('reports/staff-dashboard', [ReportController::class, 'staffDashboard']);

// ─── DESCARGA DE PDF (pública para que el <a download> funcione sin token) ──
Route::get('reports/download/{type}', [ReportController::class, 'download'])->name('reports.download');

// ─── RUTAS SOLO ADMIN ─────────────────────────────────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    Route::get('reports/admin-dashboard', [ReportController::class, 'adminDashboard']);
    Route::get('reports/financial',       [ReportController::class, 'financial']);
    Route::get('reports/inventory',       [ReportController::class, 'inventory']);
    Route::get('reports/export',          [ReportController::class, 'export'])->name('reports.export');

    Route::apiResource('expenses', ExpenseController::class);
    Route::get('expenses/summary',    [ExpenseController::class, 'summary']);
    Route::get('expenses/categories', [ExpenseController::class, 'categories']);

    Route::post('exchange-rate/update-api',    [ExchangeRateController::class, 'updateFromApi']);
    Route::post('exchange-rate/update-manual', [ExchangeRateController::class, 'updateManually']);

    Route::prefix('admin')->group(function () {
        Route::get('users',         [AuthController::class, 'index']);
        Route::post('users',        [AuthController::class, 'store']);
        Route::put('users/{id}',    [AuthController::class, 'update']);
        Route::delete('users/{id}', [AuthController::class, 'destroy']);
    });
});
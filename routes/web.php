<?php

use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

// Log viewer — solo admin
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('log-viewer', [LogViewerController::class, 'index']);
});

// SPA — todas las rutas van al blade que monta Vue
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*');
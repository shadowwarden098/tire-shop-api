<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

Route::get('log-viewer', [LogViewerController::class, 'index']);
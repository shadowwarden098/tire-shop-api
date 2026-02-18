<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ExchangeRate;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler - Tipo de cambio (cada hora)
|--------------------------------------------------------------------------
*/
Schedule::call(function () {
    ExchangeRate::updateFromApi();
})->hourly();

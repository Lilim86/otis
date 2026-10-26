<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cada mañana, avisos a los clientes (visitas próximas y cuotas). Requiere "php artisan schedule:work" o el cron de Laravel.
Schedule::command('avisos:clientes')->dailyAt('08:00')->withoutOverlapping();

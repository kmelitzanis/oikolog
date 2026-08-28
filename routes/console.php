<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recipe photos are written as soon as they're picked, so abandoned forms and
// unsaved imports leave files behind. Nothing else reclaims them.
Schedule::command('recipes:prune-images')->dailyAt('03:30');

// Barcodes scanned to check something in the shop leave products behind that
// never reach a list. Bought or hand-edited ones are never touched.
Schedule::command('products:prune')->weeklyOn(1, '03:45');

// Provider invoice mail. Hourly is plenty — a bill arrives once a month, and
// each run only queues suggestions for a human to accept.
Schedule::command('bills:scan-mail')->hourly()->withoutOverlapping();

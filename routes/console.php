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

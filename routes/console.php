<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Planification des tâches
// Schedule::command('safezone:send-reminders')
//     ->everyFiveMinutes()
//     ->withoutOverlapping()
//     ->appendOutputTo(storage_path('logs/safezone-reminders.log'));

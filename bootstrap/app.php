<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Jobs\SendSafeZoneExitReminders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tier' => \App\Http\Middleware\CheckSubscriptionTier::class,
            // V4.1 §10.2 — quota de contournement, appliqué au seul /routes/{id}/avoid
            'avoidance.quota' => \App\Http\Middleware\CheckAvoidanceQuota::class,
            'minimum-app-version' => \App\Http\Middleware\EnsureMinimumAppVersion::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Envoyer les rappels de sortie de zone de sécurité toutes les 5 minutes
        $schedule->job(new SendSafeZoneExitReminders())
            ->everyFiveMinutes()
            ->name('send-safe-zone-exit-reminders')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        ]);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'status'  => 'error',
                    'message' => 'Trop de requêtes. Réessayez dans une minute.',
                ], 429));
        });

        // POST /api/location : max 1 req/min par user (CDC : rate limiting explicite)
        RateLimiter::for('location', function (Request $request) {
            return Limit::perMinute(1)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'status'               => 'ok',
                    'alerts_nearby'        => false,
                    'next_update_interval' => 60,
                ], 200));
        });
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

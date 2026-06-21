<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        if (env('TELESCOPE_ENABLED', false)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }

        if ($this->app->environment('local', 'staging')) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Gate::define('viewPulse', function (\App\Models\User $user) {
            return $user->isAdmin();
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'status'  => 'error',
                    'message' => 'Trop de requêtes. Réessayez dans une minute.',
                ], 429));
        });

        RateLimiter::for('location', function (Request $request) {
            return Limit::perMinute(1)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'status'               => 'ok',
                    'alerts_nearby'        => false,
                    'next_update_interval' => 60,
                ], 200));
        });
    }
}

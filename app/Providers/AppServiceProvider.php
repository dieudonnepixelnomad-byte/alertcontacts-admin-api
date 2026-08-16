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

        // V4.1 §4.10 règle 4 — plafond de signalements par utilisateur et par
        // heure, contre le spam automatisé. Attaché à POST /api/v1/reports.
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perHour((int) config('incidents.rate_limit.reports_per_hour', 10))
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'status'  => 'error',
                    'message' => 'Tu as signalé beaucoup d\'incidents récemment. Réessaie dans un moment.',
                ], 429));
        });

        // V4.1 §5.4 — le routage appelle un fournisseur payant : on borne le
        // volume par utilisateur avant même le gating monétisation.
        RateLimiter::for('routing', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'status'  => 'error',
                    'message' => 'Trop de calculs d\'itinéraire. Réessaie dans un instant.',
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

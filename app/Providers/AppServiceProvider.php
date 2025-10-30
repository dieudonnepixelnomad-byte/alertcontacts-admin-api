<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        Gate::define('viewPulse', function (\App\Models\User $user) {
            return $user->isAdmin();
        });

    }
}

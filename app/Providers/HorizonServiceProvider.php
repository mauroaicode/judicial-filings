<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeMailNotificationsTo('admin@notijudicial.com');
    }

    /**
     * Register the Horizon gate.
     *
     * Allows access only to emails listed in HORIZON_ALLOWED_EMAILS (.env),
     * which is a comma-separated list: "admin@example.com,dev@example.com"
     *
     * In local environments, Horizon is always accessible.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (app()->environment('local')) {
                return true;
            }

            if ($user === null) {
                return false;
            }

            $allowed = array_filter(array_map(
                'trim',
                explode(',', (string) config('horizon.allowed_emails', ''))
            ));

            return in_array($user->email, $allowed, true);
        });
    }
}

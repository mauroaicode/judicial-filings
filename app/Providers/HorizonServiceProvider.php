<?php

namespace App\Providers;

use Illuminate\Http\Request;
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

        // API-only app: no web session on /horizon. Use token or allowed email (if logged in).
        Horizon::auth(function (Request $request): bool {
            if (app()->environment('local')) {
                return true;
            }

            $user = $request->user();
            if ($user !== null && in_array($user->email, self::allowedEmails(), true)) {
                return true;
            }

            $secret = (string) config('horizon.secret', '');
            if ($secret !== '' && hash_equals($secret, (string) $request->query('token', ''))) {
                return true;
            }

            return false;
        });
    }

    /**
     * @return list<string>
     */
    private static function allowedEmails(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('horizon.allowed_emails', ''))
        )));
    }

    /**
     * Required by HorizonApplicationServiceProvider (used if Horizon::auth is not set).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => false);
    }
}

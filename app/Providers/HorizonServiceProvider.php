<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    private const SESSION_KEY = 'horizon_authenticated';

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // API-only app: first visit with ?token=, then session cookie for SPA navigation/API.
        Horizon::auth(function (Request $request): bool {
            if (app()->environment('local')) {
                return true;
            }

            $user = $request->user();
            if ($user !== null && in_array($user->email, self::allowedEmails(), true)) {
                return true;
            }

            if (self::hasValidSession($request)) {
                return true;
            }

            $secret = (string) config('horizon.secret', '');
            if ($secret !== '' && hash_equals($secret, (string) $request->query('token', ''))) {
                self::grantSession($request);

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

    private static function grantSession(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, self::sessionFingerprint());
    }

    private static function hasValidSession(Request $request): bool
    {
        return $request->session()->get(self::SESSION_KEY) === self::sessionFingerprint();
    }

    private static function sessionFingerprint(): string
    {
        $secret = (string) config('horizon.secret', '');

        return hash('sha256', $secret.'|horizon-access');
    }

    /**
     * Required by HorizonApplicationServiceProvider (used if Horizon::auth is not set).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => false);
    }
}

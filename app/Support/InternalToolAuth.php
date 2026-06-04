<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Shared token + session auth for internal dashboards (Horizon, Log Viewer).
 */
final class InternalToolAuth
{
    private const SESSION_KEY = 'internal_tool_authenticated';

    public static function authorize(Request $request): bool
    {
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

        return hash('sha256', $secret.'|internal-tools');
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
}

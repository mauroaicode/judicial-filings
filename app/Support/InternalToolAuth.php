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

    private const TOKEN_SESSION_KEY = 'internal_tool_token';

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
        $token = self::resolveToken($request);

        if ($secret !== '' && $token !== '' && self::tokenIsValid($token)) {
            self::establishSession($request, $token);

            return true;
        }

        return false;
    }

    public static function tokenIsValid(string $token): bool
    {
        $secret = (string) config('horizon.secret', '');

        return $secret !== '' && $token !== '' && hash_equals($secret, $token);
    }

    public static function establishSession(Request $request, ?string $token = null): void
    {
        self::grantSession($request);

        if (! $request->hasSession()) {
            return;
        }

        if ($token === null || $token === '') {
            $token = self::resolveToken($request);
        }

        if ($token !== '' && self::tokenIsValid($token)) {
            $request->session()->put(self::TOKEN_SESSION_KEY, encrypt($token));
        }
    }

    public static function dashboardTokenForRequest(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        try {
            $encrypted = $request->session()->get(self::TOKEN_SESSION_KEY);

            if (! is_string($encrypted) || $encrypted === '') {
                return null;
            }

            $token = decrypt($encrypted);

            return self::tokenIsValid($token) ? $token : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function resolveToken(Request $request): string
    {
        $query = (string) $request->query('token', '');
        if ($query !== '') {
            return $query;
        }

        $sessionToken = self::dashboardTokenForRequest($request);
        if ($sessionToken !== null) {
            return $sessionToken;
        }

        $header = (string) $request->header('X-Dashboard-Token', '');
        if ($header !== '') {
            return $header;
        }

        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return '';
    }

    private static function grantSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, self::sessionFingerprint());
    }

    private static function hasValidSession(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

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

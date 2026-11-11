<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\User\Models\User;
use Symfony\Component\HttpFoundation\Response;

class Google2FAMiddleware
{
    /**
     * Block authenticated users who enabled 2FA until the token was issued by a valid code.
     *
     * Login and the 2FA endpoints stay available: the login token is not enough
     * for the rest of the API when google_2fa_enabled is true.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->bypassesTwoFactor($request)) {
            return $next($request);
        }

        $user = $request->user('sanctum');

        if (! $user instanceof AppUser && ! $user instanceof User) {
            return $next($request);
        }

        if (! $user->google_2fa_enabled) {
            return $next($request);
        }

        /** @var PersonalAccessToken|\Laravel\Sanctum\TransientToken|null $accessToken */
        $accessToken = $user->currentAccessToken();
        $abilities = $accessToken instanceof PersonalAccessToken ? $accessToken->abilities : [];

        if (! is_array($abilities) || ! in_array('2fa_verified', $abilities, true)) {
            abort(403, __('auth.2fa_required'));
        }

        return $next($request);
    }

    private function bypassesTwoFactor(Request $request): bool
    {
        return $request->is(
            'api/app-user/login',
            'api/app-user/forgot-password',
            'api/app-user/reset-password',
            'api/app-user/2fa',
            'api/app-user/2fa/*',
            'api/admin/login',
            'api/admin/2fa',
            'api/admin/2fa/*',
        );
    }
}

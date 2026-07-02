<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Domain\AppUser\Models\AppUser;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppUserOrganizationActive
{
    /**
     * Block authenticated app users whose organization is inactive.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if (! $user instanceof AppUser) {
            return $next($request);
        }

        if (! $user->belongsToActiveOrganization()) {
            return response()->json([
                'messages' => [__('auth.user_inactive')],
                'code' => 401,
            ], 401);
        }

        return $next($request);
    }
}

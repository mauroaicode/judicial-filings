<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\InternalToolAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures API requests carry X-Dashboard-Token from the server session when the
 * browser did not send it (e.g. after redirect stripped ?token= from the URL).
 */
final class InjectInternalToolTokenFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Dashboard-Token') === null) {
            $token = InternalToolAuth::dashboardTokenForRequest($request);

            if ($token !== null) {
                $request->headers->set('X-Dashboard-Token', $token);
            }
        }

        return $next($request);
    }
}

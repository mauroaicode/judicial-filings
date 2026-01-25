<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'messages' => [__('auth.unauthorized')],
                'code' => 401,
            ], 401);
        }

        // Check if user has a role with guard_name = 'admin'
        // Using method_exists to avoid type issues with PHPStan
        if (! method_exists($user, 'hasRole')) {
            return response()->json([
                'messages' => [__('auth.forbidden')],
                'code' => 403,
            ], 403);
        }

        /** @var \Src\Domain\User\Models\User $user */
        $hasAdminRole = $user->hasRole('admin', 'admin');

        if (! $hasAdminRole) {
            return response()->json([
                'messages' => [__('auth.forbidden')],
                'code' => 403,
            ], 403);
        }

        return $next($request);
    }
}

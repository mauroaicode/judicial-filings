<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Application\Shared\Presence\AppUserActivityRecorder;
use Src\Domain\AppUser\Models\AppUser;
use Symfony\Component\HttpFoundation\Response;

class RecordAppUserActivity
{
    public function __construct(
        private readonly AppUserActivityRecorder $recorder,
    ) {}

    /**
     * Runs after the rest of the pipeline so Sanctum has already resolved the user.
     * A missing bearer token (login, password reset) leaves the user empty and is ignored.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user('sanctum');

        if ($user instanceof AppUser && $response->getStatusCode() !== 401) {
            $this->recorder->record($user->id, $request->ip(), false);
        }

        return $response;
    }
}

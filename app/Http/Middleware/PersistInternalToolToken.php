<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\InternalToolAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When ?token= is valid, stores the dashboard session and redirects to the same URL
 * without the token (keeps ?file=, ?host=, etc.). Bookmarks and reloads then work
 * via session cookie instead of leaking the secret in the address bar.
 */
final class PersistInternalToolToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $token = (string) $request->query('token', '');

        if ($token === '' || ! InternalToolAuth::tokenIsValid($token)) {
            return $next($request);
        }

        InternalToolAuth::establishSession($request, $token);

        $query = $request->except('token');

        $target = $request->url().(count($query) > 0 ? '?'.http_build_query($query) : '');

        if ($target === $request->fullUrl()) {
            return $next($request);
        }

        return redirect()->to($target);
    }
}

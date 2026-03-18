<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Support\Facades\Log;

/**
 * Provides the rotating residential proxy URL for outbound HTTP requests.
 *
 * Integration: Webshare Rotating Residential — Rotating Proxy Endpoint.
 *   - A single endpoint (p.webshare.io:80) handles all requests.
 *   - Webshare assigns a random residential IP on every new connection.
 *   - Country filter is configured in the Webshare dashboard (Colombia).
 *   - No pool, no database, no round-robin — just one URL with credentials.
 *
 * Required .env variables:
 *   JUDICIAL_BRANCH_PROXY_ENABLED=true
 *   JUDICIAL_BRANCH_PROXY_HOST=p.webshare.io
 *   JUDICIAL_BRANCH_PROXY_PORT=80
 *   JUDICIAL_BRANCH_PROXY_USERNAME=wfvehrrcresidential-CO-rotate
 *   JUDICIAL_BRANCH_PROXY_PASSWORD=ab7xwhoq3eip
 */
class ProxyPoolService
{
    /**
     * Returns the proxy URL to use for the next HTTP request, or null when
     * the proxy is disabled.
     *
     * Format: http://username:password@host:port
     * Webshare rotates the residential exit IP on every new TCP connection.
     */
    public function next(string $seed = ''): ?string
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            return null;
        }

        $protocol = (string) config('judicial-branch.proxy.protocol', 'http');
        $host     = (string) config('judicial-branch.proxy.host', 'rp.scrapegw.com');
        $port     = (int)    config('judicial-branch.proxy.port', 6060);
        $username = (string) config('judicial-branch.proxy.username', '');
        $password = (string) config('judicial-branch.proxy.password', '');

        if ($username === '' || $password === '') {
            $this->logWarning('Proxy credentials not configured (JUDICIAL_BRANCH_PROXY_USERNAME / PASSWORD)');

            return null;
        }

        return "{$protocol}://{$username}:{$password}@{$host}:{$port}";
    }

    /**
     * No-op: the rotating endpoint has no individual IPs to deactivate.
     * Kept for interface compatibility with JudicialBranchConsultService.
     */
    public function markFailed(string $proxyUrl): void
    {
        $this->logWarning('Proxy connection error — Webshare will rotate to a new residential IP on next request', [
            'proxy' => $this->maskProxy($proxyUrl),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function maskProxy(string $proxy): string
    {
        return preg_replace('/(:)[^@:\/]+(@)/', '$1***$2', $proxy) ?? $proxy;
    }

    private function logWarning(string $message, array $context = []): void
    {
        Log::channel(config('judicial-branch.log_channel', 'process_import'))
            ->warning("[ProxyPool] {$message}", $context);
    }
}

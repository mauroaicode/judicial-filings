<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Provides the rotating residential proxy URL for outbound HTTP requests.
 *
 * Integration: ProxyScrape Rotating Residential gateway.
 *   - URL única con credenciales; rotación por política del proveedor.
 *   - HTTPS al puerto 448: si CONNECT HTTP falla (cURL 56), usar SOCKS5 (ver dashboard).
 *
 * Required .env variables:
 *   JUDICIAL_BRANCH_PROXY_ENABLED=true
 *   JUDICIAL_BRANCH_PROXY_PROVIDER=proxyscrape
 *   JUDICIAL_BRANCH_PROXY_PROTOCOL=http (o socks5)
 *   JUDICIAL_BRANCH_PROXY_HOST=rp.scrapegw.com
 *   JUDICIAL_BRANCH_PROXY_PORT=6060 (u otro para SOCKS5)
 *   JUDICIAL_BRANCH_PROXY_USERNAME=...
 *   JUDICIAL_BRANCH_PROXY_PASSWORD=...
 */
class ProxyPoolService
{
    /**
     * Returns the proxy URL to use for the next HTTP request, or null when
     * the proxy is disabled.
     *
     * Format: protocol://username:password@host:port
     */
    public function next(string $seed = ''): ?string
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            return null;
        }

        $protocol = (string) config('judicial-branch.proxy.protocol', 'http');
        $host = (string) config('judicial-branch.proxy.host', 'rp.scrapegw.com');
        $port = (int) config('judicial-branch.proxy.port', 6060);
        $username = (string) config('judicial-branch.proxy.username', '');
        $password = (string) config('judicial-branch.proxy.password', '');
        $provider = (string) config('judicial-branch.proxy.provider', 'proxyscrape');
        $sessionMutationEnabled = (bool) config('judicial-branch.proxy.enable_session_mutation', false);

        if ($username === '' || $password === '') {
            $this->logWarning('Proxy credentials not configured (JUDICIAL_BRANCH_PROXY_USERNAME / PASSWORD)');

            return null;
        }

        if ($sessionMutationEnabled) {
            $version = $seed !== '' ? (int) Cache::get("proxy_session_v:{$seed}", 0) : 0;

            $sessionId = $seed !== ''
                ? substr(hash('sha256', $seed.$version), 0, 10)
                : bin2hex(random_bytes(5));

            if (preg_match('/-session-[a-zA-Z0-9]+/', $username)) {
                $username = preg_replace('/-session-[a-zA-Z0-9]+/', '-session-'.$sessionId, $username);
            }
        } elseif ($provider === 'proxyscrape' && preg_match('/-session-[a-zA-Z0-9]+/', $username)) {
            $this->logWarning('Session mutation disabled for proxyscrape; using username as-is');
        }

        return "{$protocol}://{$username}:{$password}@{$host}:{$port}";
    }

    /**
     * Increments the retry version for the given seed in cache,
     * forcing next() to return a different session ID.
     */
    public function markFailed(string $seed): void
    {
        if ($seed === '') {
            return;
        }

        $key = "proxy_session_v:{$seed}";
        $newVersion = Cache::increment($key);
        Cache::put($key, $newVersion, now()->addHour());

        $this->logWarning('Proxy session marked as failed — forcing rotation on next request', [
            'seed' => $seed,
            'new_version' => $newVersion,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function logWarning(string $message, array $context = []): void
    {
        Log::channel(config('judicial-branch.log_channel', 'process_import'))
            ->warning("[ProxyPool] {$message}", $context);
    }
}

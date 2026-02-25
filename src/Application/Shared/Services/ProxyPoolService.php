<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages a rotating pool of HTTP proxies from ProxyScrape or Geonode.
 *
 * When proxy usage is enabled (JUDICIAL_BRANCH_PROXY_ENABLED=true), each HTTP
 * request to Rama Judicial is routed through a randomly selected proxy from the
 * pool, distributing requests across many IPs and bypassing per-IP rate limits.
 *
 * Provider selection is controlled by JUDICIAL_BRANCH_PROXY_PROVIDER:
 *   - "proxyscrape" → plain-text list (ip:port per line) from ProxyScrape datacenter API
 *   - "geonode"     → JSON list from Geonode free proxy API
 *
 * Proxy selection uses array_rand() so each call gets a different IP without
 * requiring shared atomic counters (which fail with the database cache driver).
 */
class ProxyPoolService
{
    private const CACHE_KEY = 'judicial_proxy_pool';

    /** @var list<string> Loaded pool of "ip:port" strings */
    private array $proxies = [];

    /**
     * Returns a random proxy URL from the pool, or null when proxies are
     * disabled or the pool is empty.
     *
     * Format returned: "http://ip:port"
     */
    public function next(): ?string
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            return null;
        }

        $this->ensurePoolLoaded();

        if ($this->proxies === []) {
            return null;
        }

        return 'http://'.$this->proxies[array_rand($this->proxies)];
    }

    /**
     * Returns how many proxies are currently loaded in the pool.
     */
    public function count(): int
    {
        $this->ensurePoolLoaded();

        return count($this->proxies);
    }

    /**
     * Clears the cached pool and forces a fresh fetch on the next call.
     */
    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->proxies = [];
        $this->ensurePoolLoaded();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function ensurePoolLoaded(): void
    {
        if ($this->proxies !== []) {
            return;
        }

        $ttlMinutes = (int) config('judicial-branch.proxy.cache_ttl_minutes', 60);

        $this->proxies = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes($ttlMinutes),
            fn (): array => $this->fetchProxies()
        );
    }

    /**
     * Fetches proxies from the configured provider.
     *
     * @return list<string>
     */
    private function fetchProxies(): array
    {
        $provider = strtolower((string) config('judicial-branch.proxy.provider', 'proxyscrape'));

        $proxies = match ($provider) {
            'geonode' => $this->fetchFromGeonode(),
            'proxyscrape' => $this->fetchFromProxyScrape(),
            default => $this->fetchFromProxyScrape(),
        };

        $this->logInfo('Proxy pool loaded', [
            'provider' => $provider,
            'count' => count($proxies),
        ]);

        return $proxies;
    }

    /**
     * Fetches from ProxyScrape datacenter shared proxy API.
     *
     * Response format: plain text, one "ip:port" per line.
     * These are HTTP datacenter proxies that support CONNECT tunneling,
     * required for HTTPS on non-standard ports (e.g. port 448 of Rama Judicial).
     *
     * @return list<string>
     */
    private function fetchFromProxyScrape(): array
    {
        try {
            $url = (string) config('judicial-branch.proxy.proxyscrape_url');

            if ($url === '' || $url === '0') {
                $this->logWarning('ProxyScrape URL not configured (judicial-branch.proxy.proxyscrape_url)');

                return [];
            }

            $response = Http::timeout(20)->get($url);

            if (! $response->successful()) {
                $this->logWarning('ProxyScrape returned non-200', ['status' => $response->status()]);

                return [];
            }

            $lines = preg_split('/\r?\n/', trim($response->body())) ?: [];

            return array_values(array_filter(
                array_map(trim(...), $lines),
                static fn (string $line): bool => (bool) preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:\d{2,5}$/', $line)
            ));
        } catch (\Throwable $e) {
            $this->logWarning('Failed to fetch from ProxyScrape', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Fetches from Geonode free proxy list API.
     *
     * Response format: JSON with a "data" array; each item has "ip" and "port".
     * Endpoint: https://proxylist.geonode.com/api/proxy-list?protocols=http&limit=500&...
     *
     * @return list<string>
     */
    private function fetchFromGeonode(): array
    {
        try {
            $url = (string) config('judicial-branch.proxy.geonode_url');

            if ($url === '' || $url === '0') {
                $this->logWarning('Geonode URL not configured (judicial-branch.proxy.geonode_url)');

                return [];
            }

            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; curl/7.0)'])
                ->get($url);

            if (! $response->successful()) {
                $this->logWarning('Geonode returned non-200', ['status' => $response->status()]);

                return [];
            }

            $data = $response->json('data', []);

            if (empty($data) || ! is_array($data)) {
                $this->logWarning('Geonode returned empty data array');

                return [];
            }

            $proxies = [];

            foreach ($data as $item) {
                $ip = $item['ip'] ?? null;
                $port = $item['port'] ?? null;
                if (! $ip) {
                    continue;
                }

                if (! $port) {
                    continue;
                }

                $proxies[] = "{$ip}:{$port}";
            }

            return $proxies;
        } catch (\Throwable $e) {
            $this->logWarning('Failed to fetch from Geonode', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function logInfo(string $message, array $context = []): void
    {
        Log::channel(config('judicial-branch.log_channel', 'process_import'))
            ->info("[ProxyPool] {$message}", $context);
    }

    private function logWarning(string $message, array $context = []): void
    {
        Log::channel(config('judicial-branch.log_channel', 'process_import'))
            ->warning("[ProxyPool] {$message}", $context);
    }
}

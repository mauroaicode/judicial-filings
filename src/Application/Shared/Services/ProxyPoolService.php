<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages a rotating pool of HTTP proxies persisted in the database.
 *
 * Rotation strategy: lock-free, seed-based.
 *   next($seed) computes a starting position from crc32($seed) % total,
 *   then scans forward for the first active proxy. Each worker uses the
 *   process number as seed, so different radicados naturally spread across
 *   different IPs without needing a shared counter or DB lock.
 *
 * Failure handling:
 *   markFailed() sets is_active=false for proxies that return cURL 7, 28 or 56.
 *   These are permanent failures (proxy dead/unreachable). 403 from Rama Judicial
 *   is NOT treated as a permanent failure — it is transient and the next retry
 *   will pick a different IP via the seed rotation.
 *
 * Pool refresh:
 *   Only triggered manually via php artisan proxy:refresh, or automatically
 *   on first run when the pool is empty. Never auto-refreshed during imports
 *   to avoid adding latency to queue workers.
 */
class ProxyPoolService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the next proxy URL for the given seed, or null when proxies
     * are disabled or the pool is completely exhausted.
     *
     * Uses crc32($seed) % total to deterministically pick a starting position,
     * then scans forward for the first active proxy. Different seeds (process
     * numbers) map to different starting positions, spreading load across IPs.
     *
     * Format returned: "http://ip:port" or "http://user:pass@ip:port"
     */
    public function next(string $seed = ''): ?string
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            return null;
        }

        $this->ensurePoolReady();

        $total = (int) DB::table('proxy_pool_state')->value('total_count');

        if ($total === 0) {
            return null;
        }

        $startPosition = $seed !== ''
            ? (abs(crc32($seed)) % $total)
            : random_int(0, $total - 1);

        // Scan forward from startPosition for the first active proxy
        $candidate = DB::table('proxy_pool_entries')
            ->where('is_active', true)
            ->where('position', '>=', $startPosition)
            ->orderBy('position')
            ->first();

        // Wrap around to beginning if nothing found from startPosition onward
        if ($candidate === null) {
            $candidate = DB::table('proxy_pool_entries')
                ->where('is_active', true)
                ->orderBy('position')
                ->first();
        }

        if ($candidate === null) {
            $this->logWarning('All proxies exhausted — pool has no active entries');

            return null;
        }

        $this->logInfo('Proxy selected (round-robin)', [
            'proxy'        => $this->maskProxy($candidate->proxy_address),
            'position'     => $candidate->position,
            'active_count' => $this->count(),
            'total_count'  => $total,
        ]);

        $proxyBase = $candidate->proxy_address;

        // If configured for IP auth but DB has credentials (out of sync), strip credentials
        if (config('judicial-branch.proxy.webshare_auth_mode', 'ip') === 'ip' && str_contains($proxyBase, '@')) {
            $proxyBase = explode('@', $proxyBase)[1] ?? $proxyBase;
        }

        return 'http://'.$proxyBase;
    }

    /**
     * Marks a proxy as permanently inactive after a cURL 7, 28 or 56 failure.
     * These errors mean the proxy itself is dead or unreachable — not a
     * temporary block by Rama Judicial.
     */
    public function markFailed(string $proxyUrl): void
    {
        $proxy = str_starts_with($proxyUrl, 'http://')
            ? substr($proxyUrl, 7)
            : $proxyUrl;

        $updated = DB::table('proxy_pool_entries')
            ->where('proxy_address', $proxy)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

        if ($updated > 0) {
            DB::table('proxy_pool_state')
                ->where('id', 1)
                ->decrement('active_count');

            $this->logWarning('Proxy marked as failed (cURL error)', [
                'proxy' => $this->maskProxy($proxy),
            ]);
        }
    }

    /**
     * Forces a fresh fetch from Webshare, upserts entries preserving
     * is_active state, and recalculates counts.
     */
    public function refresh(): void
    {
        $this->logInfo('Refreshing proxy pool from provider...');

        $proxies = $this->fetchFromWebshare();

        if (empty($proxies)) {
            $this->logWarning('Webshare returned empty proxy list — pool not updated');

            return;
        }

        $this->persistPool($proxies);

        $this->logInfo('Proxy pool refreshed', ['count' => count($proxies)]);
    }

    /**
     * Returns the number of currently active proxies.
     */
    public function count(): int
    {
        return (int) (DB::table('proxy_pool_state')->where('id', 1)->value('active_count') ?? 0);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Initializes the pool on first run. Only fetches from Webshare when the
     * pool is completely empty — never during normal import operation.
     */
    private function ensurePoolReady(): void
    {
        $activeCount = (int) (DB::table('proxy_pool_state')->where('id', 1)->value('active_count') ?? 0);

        if ($activeCount === 0) {
            $this->logInfo('Pool is empty — fetching from Webshare');
            $this->refresh();
        }
    }

    /**
     * Fetches all proxies from Webshare API (all pages).
     *
     * Supports two auth modes:
     *   "ip"          → stored as "ip:port"          (IP Authorization in Webshare)
     *   "credentials" → stored as "user:pass@ip:port" (Username/Password in Webshare)
     *
     * @return list<array{id: string, proxy: string}>
     */
    private function fetchFromWebshare(): array
    {
        $apiKey   = (string) config('judicial-branch.proxy.webshare_api_key', '');
        $authMode = strtolower((string) config('judicial-branch.proxy.webshare_auth_mode', 'ip'));

        if ($apiKey === '') {
            $this->logWarning('Webshare API key not configured (JUDICIAL_BRANCH_PROXY_WEBSHARE_API_KEY)');

            return [];
        }

        $pageSize   = 100;
        $page       = 1;
        $allProxies = [];

        try {
            do {
                $url = "https://proxy.webshare.io/api/v2/proxy/list/?mode=direct&page={$page}&page_size={$pageSize}";

                $response = Http::timeout(20)
                    ->withHeaders(['Authorization' => "Token {$apiKey}"])
                    ->get($url);

                if (! $response->successful()) {
                    $this->logWarning('Webshare API returned non-200', [
                        'status' => $response->status(),
                        'page'   => $page,
                    ]);
                    break;
                }

                $data    = $response->json();
                $results = $data['results'] ?? [];

                foreach ($results as $item) {
                    if (! ($item['valid'] ?? false)) {
                        continue;
                    }

                    $proxyId  = $item['id'] ?? null;
                    $username = $item['username'] ?? null;
                    $password = $item['password'] ?? null;
                    $ip       = $item['proxy_address'] ?? null;
                    $port     = $item['port'] ?? null;

                    if (! $proxyId || ! $ip || ! $port) {
                        continue;
                    }

                    $proxyString = ($authMode === 'credentials' && $username && $password)
                        ? "{$username}:{$password}@{$ip}:{$port}"
                        : "{$ip}:{$port}";

                    $allProxies[] = ['id' => $proxyId, 'proxy' => $proxyString];
                }

                $hasNextPage = ! empty($data['next']);
                $page++;

            } while ($hasNextPage);

            $this->logInfo('Webshare fetch complete', ['total' => count($allProxies)]);

        } catch (\Throwable $e) {
            $this->logWarning('Failed to fetch from Webshare', ['error' => $e->getMessage()]);
        }

        return $allProxies;
    }

    /**
     * Upserts the proxy list preserving is_active state for existing entries.
     * New proxies are inserted as active=true.
     * Proxies no longer in the provider list are deleted.
     *
     * @param  list<array{id: string, proxy: string}>  $proxies
     */
    private function persistPool(array $proxies): void
    {
        $now        = now();
        $incomingIds = array_column($proxies, 'id');

        // Remove proxies no longer in Webshare (use delete, not truncate — avoids implicit commit)
        DB::table('proxy_pool_entries')
            ->whereNotIn('proxy_id', $incomingIds)
            ->delete();

        // Upsert: insert new as active, update address/position for existing (preserve is_active)
        $rows = [];

        foreach ($proxies as $position => $item) {
            $rows[] = [
                'proxy_id'      => $item['id'],
                'proxy_address' => $item['proxy'],
                'position'      => $position,
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('proxy_pool_entries')->upsert(
                $chunk,
                ['proxy_id'],
                ['proxy_address', 'position', 'updated_at'], // is_active NOT updated on conflict
            );
        }

        $totalCount  = count($proxies);
        $activeCount = DB::table('proxy_pool_entries')->where('is_active', true)->count();

        DB::table('proxy_pool_state')->upsert(
            [
                'id'               => 1,
                'current_position' => 0,
                'total_count'      => $totalCount,
                'active_count'     => $activeCount,
                'provider'         => 'webshare',
                'last_fetched_at'  => $now,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            ['id'],
            ['total_count', 'active_count', 'provider', 'last_fetched_at', 'updated_at'],
        );
    }

    private function maskProxy(string $proxy): string
    {
        return preg_replace('/(:)[^@]+(@)/', '$1***$2', $proxy) ?? $proxy;
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

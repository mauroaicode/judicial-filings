<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages a rotating pool of HTTP proxies using a round-robin strategy
 * persisted in the database.
 *
 * On each call to next(), the service atomically advances a pointer in
 * proxy_pool_state and returns the proxy at that position from
 * proxy_pool_entries. This guarantees sequential, non-repeating rotation
 * across concurrent workers without relying on the cache driver.
 *
 * The proxy list is fetched from Webshare once and stored in the DB.
 * It is only re-fetched when:
 *   1. The pool is empty (first run or after a manual refresh).
 *   2. The ratio of active proxies drops below the configured minimum
 *      (proxy_pool_min_active_ratio, default 0.70).
 *   3. A manual php artisan proxy:refresh is executed.
 *
 * Proxy format stored: "username:password@ip:port"
 * Proxy format returned to cURL: "http://username:password@ip:port"
 */
class ProxyPoolService
{
    public function __construct() {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the next proxy URL in round-robin order, or null when proxies
     * are disabled or the pool is empty.
     *
     * Format returned: "http://user:pass@ip:port"
     */
    public function next(): ?string
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            return null;
        }

        $this->ensurePoolReady();

        return DB::transaction(function (): ?string {
            $state = DB::table('proxy_pool_state')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (! $state || $state->active_count === 0) {
                return null;
            }

            $totalCount = (int) $state->total_count;
            $startPosition = (int) $state->current_position;

            // Find the next active proxy starting from current_position,
            // wrapping around if needed. Tries up to total_count positions.
            $proxy = null;
            $nextPosition = $startPosition;

            for ($i = 0; $i < $totalCount; $i++) {
                $candidate = DB::table('proxy_pool_entries')
                    ->where('position', $nextPosition % $totalCount)
                    ->where('is_active', true)
                    ->first();

                if ($candidate !== null) {
                    $proxy = $candidate->proxy_address;
                    $nextPosition = ($nextPosition % $totalCount) + 1;
                    break;
                }

                $nextPosition++;
            }

            if ($proxy === null) {
                return null;
            }

            DB::table('proxy_pool_state')
                ->where('id', 1)
                ->update(['current_position' => $nextPosition % $totalCount]);

            $this->logInfo('Proxy selected (round-robin)', [
                'proxy' => $this->maskProxy($proxy),
                'position' => ($nextPosition - 1 + $totalCount) % $totalCount,
                'active_count' => $state->active_count,
                'total_count' => $totalCount,
            ]);

            return 'http://'.$proxy;
        });
    }

    /**
     * Marks a proxy as inactive after a cURL 7 or cURL 28 failure.
     * Also decrements active_count in proxy_pool_state.
     */
    public function markFailed(string $proxyUrl): void
    {
        // Strip only the exact "http://" prefix to match stored format.
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

            $this->logWarning('Proxy marked as failed', [
                'proxy' => $this->maskProxy($proxy),
            ]);
        }
    }

    /**
     * Forces a fresh fetch from the provider, truncates the pool and resets
     * the round-robin pointer to 0.
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
     * Returns the number of active proxies currently in the pool.
     */
    public function count(): int
    {
        return (int) (DB::table('proxy_pool_state')->where('id', 1)->value('active_count') ?? 0);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Ensures the pool has at least one active proxy.
     *
     * Only fetches from the provider when the pool is completely empty (first
     * run or after a manual php artisan proxy:refresh that wiped entries).
     *
     * Auto-refresh by active-ratio is intentionally removed: calling the
     * Webshare API (~10 s) inside every next() call during an import adds
     * massive latency. Use php artisan proxy:validate-rama before importing
     * to pre-clean the pool, and php artisan proxy:refresh to replenish IPs.
     */
    private function ensurePoolReady(): void
    {
        $state = DB::table('proxy_pool_state')->where('id', 1)->first();

        if ($state === null || (int) $state->active_count === 0) {
            $this->logInfo('Pool is empty — fetching from Webshare');
            $this->refresh();
        }
    }

    /**
     * Fetches all proxies from Webshare API, iterating through all pages.
     *
     * Response format per item:
     * {
     *   "id": "d-17329297559",
     *   "username": "wfvehrrc",
     *   "password": "ab7xwhoq3eip",
     *   "proxy_address": "38.154.217.40",
     *   "port": 7231,
     *   "valid": true,
     *   ...
     * }
     *
     * Stored format: "username:password@proxy_address:port"
     *
     * @return list<array{id: string, proxy: string}>
     */
    private function fetchFromWebshare(): array
    {
        $apiKey = (string) config('judicial-branch.proxy.webshare_api_key');
        $authMode = strtolower((string) config('judicial-branch.proxy.webshare_auth_mode', 'ip'));

        if ($apiKey === '' || $apiKey === '0') {
            $this->logWarning('Webshare API key not configured (JUDICIAL_BRANCH_PROXY_WEBSHARE_API_KEY)');

            return [];
        }

        $pageSize = 100;
        $page = 1;
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
                        'page' => $page,
                    ]);

                    break;
                }

                $data = $response->json();
                $results = $data['results'] ?? [];

                foreach ($results as $item) {
                    if (! ($item['valid'] ?? false)) {
                        continue;
                    }

                    $proxyId = $item['id'] ?? null;
                    $username = $item['username'] ?? null;
                    $password = $item['password'] ?? null;
                    $ip = $item['proxy_address'] ?? null;
                    $port = $item['port'] ?? null;

                    if (! $proxyId || ! $ip || ! $port) {
                        continue;
                    }

                    $proxyString = "{$ip}:{$port}";

                    if ($authMode === 'credentials' && $username && $password) {
                        $proxyString = "{$username}:{$password}@{$ip}:{$port}";
                    }

                    $allProxies[] = [
                        'id' => $proxyId,
                        'proxy' => $proxyString,
                    ];
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
     * Persists the proxy list using upsert to preserve existing is_active state.
     *
     * - New proxies from the provider are inserted as active.
     * - Existing proxies keep their current is_active value (failed ones stay failed).
     * - Proxies no longer returned by the provider are removed.
     * - active_count is recalculated from the actual DB state after the upsert.
     *
     * This prevents the auto-refresh loop where refreshing resets all failed
     * proxies back to active, only to have them fail again immediately.
     *
     * @param  list<array{id: string, proxy: string}>  $proxies
     */
    private function persistPool(array $proxies): void
    {
        $now = now();
        $incomingIds = array_column($proxies, 'id');

        DB::transaction(function () use ($proxies, $incomingIds, $now): void {
            // Remove proxies no longer in the provider list
            DB::table('proxy_pool_entries')
                ->whereNotIn('proxy_id', $incomingIds)
                ->delete();

            // Upsert: insert new proxies as active, update address/position for existing ones
            // but do NOT touch is_active so validated failures are preserved.
            $rows = [];

            foreach ($proxies as $position => $item) {
                $rows[] = [
                    'proxy_id' => $item['id'],
                    'proxy_address' => $item['proxy'],
                    'position' => $position,
                    'is_active' => true,   // only applied on INSERT, not on UPDATE
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('proxy_pool_entries')->upsert(
                    $chunk,
                    ['proxy_id'],                          // unique key
                    ['proxy_address', 'position', 'updated_at'], // update these on conflict, NOT is_active
                );
            }

            // Recalculate active_count from actual DB state
            $totalCount = count($proxies);
            $activeCount = DB::table('proxy_pool_entries')->where('is_active', true)->count();

            DB::table('proxy_pool_state')->upsert(
                [
                    'id' => 1,
                    'current_position' => 0,
                    'total_count' => $totalCount,
                    'active_count' => $activeCount,
                    'provider' => config('judicial-branch.proxy.provider', 'webshare'),
                    'last_fetched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['id'],
                ['total_count', 'active_count', 'provider', 'last_fetched_at', 'updated_at'],
                // current_position intentionally NOT reset so round-robin continues where it left off
            );
        });
    }

    /**
     * Masks the password portion of a proxy string for safe logging.
     *
     * "user:secret@1.2.3.4:8080" → "user:***@1.2.3.4:8080"
     */
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

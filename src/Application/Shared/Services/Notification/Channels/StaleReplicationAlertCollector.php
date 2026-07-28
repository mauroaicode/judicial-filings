<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Collects radicados whose Rama Judicial detail reports a stale data-replication timestamp
 * ({@see ultimaActualizacion} vs {@see fechaConsulta}) during a sync cycle, for a single
 * Discord summary when the batch finishes.
 *
 * @phpstan-type StaleReplicationItem array{
 *     process_number: string,
 *     consulted_at: string,
 *     replicated_at: string,
 *     lag_hours: int,
 *     court: string|null
 * }
 */
final class StaleReplicationAlertCollector
{
    private const CACHE_PREFIX = 'judicial_sync:stale_replication:';

    private const CACHE_TTL_HOURS = 36;

    /**
     * @param  StaleReplicationItem  $item
     */
    public function remember(array $item): void
    {
        $key = $this->cacheKey();
        $lock = Cache::lock($key.':lock', 10);

        $lock->block(5, function () use ($key, $item): void {
            /** @var array<string, StaleReplicationItem> $items */
            $items = Cache::get($key, []);
            $items[$item['process_number']] = $item;
            Cache::put($key, $items, now()->addHours(self::CACHE_TTL_HOURS));
        });
    }

    /**
     * @return list<StaleReplicationItem>
     */
    public function pullAll(): array
    {
        $key = $this->cacheKey();
        /** @var array<string, StaleReplicationItem> $items */
        $items = Cache::pull($key, []);

        $list = array_values($items);
        usort(
            $list,
            static fn (array $a, array $b): int => $b['lag_hours'] <=> $a['lag_hours']
        );

        return $list;
    }

    public function cacheKey(?Carbon $day = null): string
    {
        $day ??= Date::now();

        return self::CACHE_PREFIX.$day->timezone((string) config('app.timezone'))->format('Y-m-d');
    }
}

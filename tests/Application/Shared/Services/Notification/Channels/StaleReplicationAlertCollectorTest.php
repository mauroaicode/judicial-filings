<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Src\Application\Shared\Services\Notification\Channels\StaleReplicationAlertCollector;

beforeEach(function (): void {
    Cache::flush();
});

it('stores unique stale replication items by process number', function (): void {
    $collector = app(StaleReplicationAlertCollector::class);

    $collector->remember([
        'process_number' => '76001310500820250023200',
        'consulted_at' => '2026-07-28 10:00:00 COT',
        'replicated_at' => '2026-07-22 10:00:00 COT',
        'lag_hours' => 144,
        'court' => 'JUZGADO 008 LABORAL',
    ]);

    $collector->remember([
        'process_number' => '76001310500820250023200',
        'consulted_at' => '2026-07-28 11:00:00 COT',
        'replicated_at' => '2026-07-21 10:00:00 COT',
        'lag_hours' => 168,
        'court' => 'JUZGADO 008 LABORAL',
    ]);

    $collector->remember([
        'process_number' => '76109333300220240012000',
        'consulted_at' => '2026-06-17 13:10:20 COT',
        'replicated_at' => '2026-06-12 18:33:11 COT',
        'lag_hours' => 114,
        'court' => null,
    ]);

    $items = $collector->pullAll();

    expect($items)->toHaveCount(2)
        ->and($items[0]['process_number'])->toBe('76001310500820250023200')
        ->and($items[0]['lag_hours'])->toBe(168)
        ->and($items[1]['process_number'])->toBe('76109333300220240012000');

    expect($collector->pullAll())->toBe([]);
});

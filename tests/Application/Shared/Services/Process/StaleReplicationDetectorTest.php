<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Notification\Channels\StaleReplicationAlertCollector;
use Src\Application\Shared\Services\Process\StaleReplicationDetector;
use Src\Domain\Process\Models\Process;

beforeEach(function (): void {
    Cache::flush();
    config([
        'judicial-sync.replication_staleness.enabled' => true,
        'judicial-sync.replication_staleness.stale_after_hours' => 24,
    ]);
});

it('remembers radicado when replication lags beyond threshold', function (): void {
    $detector = app(StaleReplicationDetector::class);

    $detector->evaluateDetailPayload('76109333300220240012000', [
        'fechaConsulta' => '2026-06-17T13:10:20.01',
        'ultimaActualizacion' => '2026-06-12T18:33:11.5',
    ], 'JUZGADO 002 ADMINISTRATIVO DE BUENAVENTURA');

    $items = app(StaleReplicationAlertCollector::class)->pullAll();

    expect($items)->toHaveCount(1)
        ->and($items[0]['process_number'])->toBe('76109333300220240012000')
        ->and($items[0]['lag_hours'])->toBeGreaterThanOrEqual(24);
});

it('ignores healthy replication lag under threshold', function (): void {
    $detector = app(StaleReplicationDetector::class);

    $detector->evaluateDetailPayload('76109333300220240012000', [
        'fechaConsulta' => '2026-07-28T08:59:46.52',
        'ultimaActualizacion' => '2026-07-28T08:48:29.863',
    ], null);

    expect(app(StaleReplicationAlertCollector::class)->pullAll())->toBe([]);
});

it('does nothing when feature is disabled', function (): void {
    config(['judicial-sync.replication_staleness.enabled' => false]);

    app(StaleReplicationDetector::class)->evaluateDetailPayload('76109333300220240012000', [
        'fechaConsulta' => '2026-06-17T13:10:20.01',
        'ultimaActualizacion' => '2026-06-12T18:33:11.5',
    ]);

    expect(app(StaleReplicationAlertCollector::class)->pullAll())->toBe([]);
});

it('fetches detail and evaluates for a process', function (): void {
    $process = Process::factory()->create([
        'process_id' => 9_001_001_001,
        'process_number' => '76109333300220240012000',
        'court' => 'JUZGADO 002 ADMINISTRATIVO',
    ]);

    $judicial = Mockery::mock(JudicialBranchConsultService::class);
    $judicial->shouldReceive('fetchDetailProcess')
        ->once()
        ->with(9_001_001_001)
        ->andReturn((object) [
            'isSuccessful' => true,
            'data' => [
                'fechaConsulta' => '2026-06-17T13:10:20.01',
                'ultimaActualizacion' => '2026-06-12T18:33:11.5',
            ],
        ]);

    $detector = new StaleReplicationDetector($judicial, app(StaleReplicationAlertCollector::class));
    $detector->evaluateRadicado($process->process_number, $process);

    expect(app(StaleReplicationAlertCollector::class)->pullAll())->toHaveCount(1);
});

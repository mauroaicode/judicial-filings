<?php

declare(strict_types=1);

use Src\Application\Shared\Services\Process\BackfillIncompleteSamaiProcessesService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessDataSource;

it('selects incomplete SAMAI processes missing court or process class', function (): void {
    Process::query()->delete();

    $samaiId = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::Samai);

    $incomplete = Process::factory()->create([
        'process_number' => '76001333301320160005700',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7600133',
        'court' => '',
        'process_class' => '',
        'is_manual_sync' => false,
    ]);

    Process::factory()->create([
        'process_number' => '76001333301320160009900',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7600133',
        'court' => 'Juzgado 14 Administrativo de Cali',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'is_manual_sync' => false,
    ]);

    Process::factory()->create([
        'process_number' => '76001333301320160008800',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7600133',
        'court' => '',
        'process_class' => '',
        'is_manual_sync' => true,
    ]);

    $service = app(BackfillIncompleteSamaiProcessesService::class);
    $candidates = $service->queryCandidates(onlyIncomplete: true);

    expect($candidates)->toHaveCount(1)
        ->and($candidates->first()?->id)->toBe($incomplete->id);
});

it('repairs candidates through ProcessSyncService backfill', function (): void {
    Process::query()->delete();

    $samaiId = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::Samai);
    $process = Process::factory()->create([
        'process_number' => '76001333301320160005700',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7600133',
        'court' => '',
        'process_class' => '',
        'is_manual_sync' => false,
    ]);

    $sync = Mockery::mock(ProcessSyncService::class);
    $sync->shouldReceive('backfillSamaiProcess')
        ->once()
        ->with(Mockery::on(fn (Process $p): bool => $p->id === $process->id), false)
        ->andReturn([
            'metadata_updated' => true,
            'actions_before' => 7,
            'actions_after' => 97,
            'actions_added' => 90,
            'subjects_added' => 0,
            'actuaciones_fetched' => 97,
        ]);

    $service = new BackfillIncompleteSamaiProcessesService($sync);
    $summary = $service->handle(onlyIncomplete: true, dryRun: false, notify: false);

    expect($summary['scanned'])->toBe(1)
        ->and($summary['repaired'])->toBe(1)
        ->and($summary['metadata_updated'])->toBe(1)
        ->and($summary['actions_added'])->toBe(90)
        ->and($summary['failed'])->toBe(0);
});

it('lists the target radicado with samai:backfill-incomplete --dry-run', function (): void {
    Process::query()->delete();

    $samaiId = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::Samai);
    Process::factory()->create([
        'process_number' => '76001333301320160005700',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7600133',
        'court' => '',
        'process_class' => '',
        'is_manual_sync' => false,
    ]);

    $this->artisan('samai:backfill-incomplete', [
        '--radicado' => '76001333301320160005700',
        '--dry-run' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('76001333301320160005700')
        ->expectsOutputToContain('[DRY RUN]');
});

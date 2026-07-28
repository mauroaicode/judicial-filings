<?php

declare(strict_types=1);

use Src\Application\Shared\Services\Process\BackfillIncompleteSamaiProcessesService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
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

it('selects SAMAI processes with incomplete history or truncated annotations', function (): void {
    Process::query()->delete();

    $samaiId = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::Samai);

    $incompleteHistory = Process::factory()->create([
        'process_number' => '76001333301420150045700',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7600133',
        'court' => 'Juzgado 14 Administrativo de Cali',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'is_manual_sync' => false,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $incompleteHistory->id,
        'action_registration_id' => 61,
        'cons_action' => 61,
        'annotation' => 'OK',
    ]);

    $truncated = Process::factory()->create([
        'process_number' => '76001333301320160005700',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7600133',
        'court' => 'Juzgado 13 Administrativo de Cali',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'is_manual_sync' => false,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $truncated->id,
        'action_registration_id' => 1,
        'cons_action' => 1,
        'annotation' => 'El Señor(a):HÉCTOR JAIME GIRALDO DUQUE con vincula...',
    ]);

    $genericCourt = Process::factory()->create([
        'process_number' => '73001333300720210004100',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7300133',
        'court' => 'Juzgado Administrativo - Ibague (tolima)',
        'process_class' => 'NULIDAD',
        'is_manual_sync' => false,
    ]);

    $complete = Process::factory()->create([
        'process_number' => '76001333301320160009900',
        'process_data_source_id' => $samaiId,
        'samai_corporacion' => '7600133',
        'court' => 'Juzgado 14 Administrativo de Cali',
        'process_class' => 'ACCION DE REPARACION DIRECTA',
        'is_manual_sync' => false,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $complete->id,
        'action_registration_id' => 1,
        'cons_action' => 1,
        'annotation' => 'Texto completo sin truncar',
    ]);

    $service = app(BackfillIncompleteSamaiProcessesService::class);
    $candidates = $service->queryCandidates(onlyIncomplete: true);

    expect($candidates->pluck('id')->all())
        ->toContain($incompleteHistory->id, $truncated->id, $genericCourt->id)
        ->not->toContain($complete->id);
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

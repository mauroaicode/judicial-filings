<?php

declare(strict_types=1);

use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Application\Admin\Process\Services\OrganizationProcessExportActionQueryService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('yields actuación rows scoped to the organization and date range', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create([
        'process_number' => '76001333301720230004444',
        'court' => 'juzgado prueba',
    ]);

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2026-02-10',
        'registration_date' => '2026-02-10',
        'action' => 'Auto de prueba',
        'annotation' => 'Nota A',
        'cons_action' => 1,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => '2025-01-01',
        'registration_date' => '2025-01-01',
        'action' => 'Fuera de rango',
        'cons_action' => 2,
    ]);

    $otherOrgProcess = Process::factory()->create();
    $otherOrgProcess->organizations()->attach(Organization::factory()->create()->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);
    ProcessAction::factory()->create([
        'process_id' => $otherOrgProcess->id,
        'action_date' => '2026-02-10',
        'cons_action' => 3,
    ]);

    $filters = OrganizationProcessExportData::hydrate([
        'include_actions' => true,
        'actions_from' => '2026-01-01',
        'actions_to' => '2026-12-31',
    ]);

    $rows = iterator_to_array(
        app(OrganizationProcessExportActionQueryService::class)->yieldRows($organization->id, $filters)
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Número de radicado'])->toBe('76001333301720230004444')
        ->and($rows[0]['Despacho'])->toBe('JUZGADO PRUEBA')
        ->and($rows[0]['Actuación'])->toBe('Auto de prueba')
        ->and($rows[0]['Fecha actuación'])->toBe('2026-02-10');
});

it('returns no rows when include_actions is false', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);
    ProcessAction::factory()->create(['process_id' => $process->id]);

    $filters = OrganizationProcessExportData::hydrate(['include_actions' => false]);

    $rows = iterator_to_array(
        app(OrganizationProcessExportActionQueryService::class)->yieldRows($organization->id, $filters)
    );

    expect($rows)->toBeEmpty();
});

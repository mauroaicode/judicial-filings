<?php

declare(strict_types=1);

use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Application\Admin\Process\Support\OrganizationProcessExportQueryService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

it('formats multiple plaintiffs like the datagrip export query', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create([
        'process_number' => '76001333301720230003301',
        'court' => 'juzgado 01',
        'process_class' => 'civil',
    ]);

    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'MARIA GARCIA',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'JUAN PEREZ',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'EMPRESA XYZ',
    ]);
    ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Tercero',
        'name_or_business_name' => 'APODERADO UNO',
    ]);

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
        'lawyer_role' => ProcessLawyerRole::DEFENDANT->value,
    ]);

    $filters = OrganizationProcessExportData::from([
        'status' => 'active',
        'include_plaintiffs' => true,
        'include_defendants' => true,
        'include_other_subjects' => true,
    ]);

    $rows = iterator_to_array(
        app(OrganizationProcessExportQueryService::class)->yieldRows($organization->id, $filters)
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Número de radicado'])->toBe('76001333301720230003301')
        ->and($rows[0]['Despacho'])->toBe('JUZGADO 01')
        ->and($rows[0]['Clase de proceso'])->toBe('CIVIL')
        ->and($rows[0]['Rol abogado'])->toBe('Demandado')
        ->and($rows[0]['Estado'])->toBe('Activo')
        ->and($rows[0]['Demandante'])->toBe('JUAN PEREZ (+1)')
        ->and($rows[0]['Demandado'])->toBe('EMPRESA XYZ')
        ->and($rows[0]['Otros sujetos'])->toBe('APODERADO UNO');
});

it('omits subject columns when not requested', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    ProcessSubject::factory()->forProcess($process)->plaintiff()->create([
        'name_or_business_name' => 'SOLO DEMANDANTE',
    ]);

    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);

    $filters = OrganizationProcessExportData::from([
        'include_plaintiffs' => false,
        'include_defendants' => false,
        'include_other_subjects' => false,
    ]);

    $rows = iterator_to_array(
        app(OrganizationProcessExportQueryService::class)->yieldRows($organization->id, $filters)
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->not->toHaveKey('Demandante')
        ->and($rows[0])->not->toHaveKey('Demandado')
        ->and($rows[0])->not->toHaveKey('Otros sujetos');
});

it('filters by organization process status', function (): void {
    $organization = Organization::factory()->create();

    $active = Process::factory()->create(['process_number' => '11111111111111111111111']);
    $inactive = Process::factory()->create(['process_number' => '22222222222222222222222']);

    $active->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);
    $inactive->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => false,
        'status' => 'inactive',
    ]);

    $filters = OrganizationProcessExportData::from(['status' => 'inactive']);

    $rows = iterator_to_array(
        app(OrganizationProcessExportQueryService::class)->yieldRows($organization->id, $filters)
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Número de radicado'])->toBe('22222222222222222222222')
        ->and($rows[0]['Estado'])->toBe('Inactivo');
});

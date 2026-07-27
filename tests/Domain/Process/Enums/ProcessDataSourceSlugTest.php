<?php

declare(strict_types=1);

use Src\Domain\Process\Enums\ProcessDataSourceSlug;

it('exposes all slug values', function (): void {
    expect(ProcessDataSourceSlug::values())->toBe([
        'judicial_branch',
        'samai',
        'publicaciones_procesales',
    ]);
});

it('marks api consultation sources', function (): void {
    expect(ProcessDataSourceSlug::JudicialBranch->isApiConsultation())->toBeTrue()
        ->and(ProcessDataSourceSlug::Samai->isApiConsultation())->toBeTrue()
        ->and(ProcessDataSourceSlug::PublicacionesProcesales->isApiConsultation())->toBeFalse();
});

it('marks private excel import sources', function (): void {
    expect(ProcessDataSourceSlug::PublicacionesProcesales->allowsPrivateExcelImport())->toBeTrue()
        ->and(ProcessDataSourceSlug::Samai->allowsPrivateExcelImport())->toBeTrue()
        ->and(ProcessDataSourceSlug::JudicialBranch->allowsPrivateExcelImport())->toBeFalse();
});

it('lists private excel and api consultation value sets', function (): void {
    expect(ProcessDataSourceSlug::privateExcelImportValues())
        ->toContain('samai', 'publicaciones_procesales')
        ->and(ProcessDataSourceSlug::privateExcelImportValues())
        ->not->toContain('judicial_branch');

    expect(ProcessDataSourceSlug::apiConsultationValues())
        ->toContain('judicial_branch', 'samai')
        ->and(ProcessDataSourceSlug::apiConsultationValues())
        ->not->toContain('publicaciones_procesales');
});

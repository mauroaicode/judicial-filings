<?php

declare(strict_types=1);

use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\ProcessDataSource;

it('filters active process data sources', function (): void {
    ProcessDataSource::query()->where('slug', 'inactive_qb_source')->delete();
    ProcessDataSource::query()->create([
        'slug' => 'inactive_qb_source',
        'name' => 'Inactive QB',
        'is_active' => false,
    ]);

    $slugs = ProcessDataSource::query()
        ->whereActive()
        ->pluck('slug')
        ->all();

    expect($slugs)->toContain(
        ProcessDataSourceSlug::JudicialBranch->value,
        ProcessDataSourceSlug::Samai->value,
        ProcessDataSourceSlug::PublicacionesProcesales->value,
    )->and($slugs)->not->toContain('inactive_qb_source');
});

it('filters private excel import sources', function (): void {
    $slugs = ProcessDataSource::query()
        ->whereActive()
        ->forPrivateExcelImport()
        ->pluck('slug')
        ->all();

    expect($slugs)->toContain(
        ProcessDataSourceSlug::Samai->value,
        ProcessDataSourceSlug::PublicacionesProcesales->value,
    )->and($slugs)->not->toContain(ProcessDataSourceSlug::JudicialBranch->value);
});

it('filters api consultation sources', function (): void {
    $slugs = ProcessDataSource::query()
        ->whereActive()
        ->forApiConsultation()
        ->pluck('slug')
        ->all();

    expect($slugs)->toContain(
        ProcessDataSourceSlug::JudicialBranch->value,
        ProcessDataSourceSlug::Samai->value,
    )->and($slugs)->not->toContain(ProcessDataSourceSlug::PublicacionesProcesales->value);
});

it('orders sources by name', function (): void {
    $names = ProcessDataSource::query()
        ->whereActive()
        ->orderedByName()
        ->pluck('name')
        ->all();

    $sorted = $names;
    sort($sorted);

    expect($names)->toBe($sorted);
});

it('filters by slug', function (): void {
    $source = ProcessDataSource::query()
        ->whereSlug(ProcessDataSourceSlug::PublicacionesProcesales->value)
        ->first();

    expect($source)->not->toBeNull()
        ->and($source->slug)->toBe(ProcessDataSourceSlug::PublicacionesProcesales->value)
        ->and($source->name)->toBe('Publicaciones Procesales');
});

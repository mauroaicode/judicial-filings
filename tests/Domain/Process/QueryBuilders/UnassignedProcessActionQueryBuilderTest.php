<?php

declare(strict_types=1);

use Src\Domain\Process\Models\UnassignedProcessAction;

beforeEach(function (): void {
    UnassignedProcessAction::query()->delete();
});

it('filters by process number', function (): void {
    UnassignedProcessAction::query()->create([
        'process_number' => '76364400300120260040900',
        'action' => 'Auto de prueba A',
        'dedupe_hash' => hash('sha256', 'a'),
    ]);
    UnassignedProcessAction::query()->create([
        'process_number' => '76364400300120260041000',
        'action' => 'Auto de prueba B',
        'dedupe_hash' => hash('sha256', 'b'),
    ]);

    $results = UnassignedProcessAction::query()
        ->whereProcessNumber('76364400300120260040900')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->process_number)->toBe('76364400300120260040900');
});

it('filters unassigned rows', function (): void {
    $pending = UnassignedProcessAction::query()->create([
        'process_number' => '76364400300120260040900',
        'action' => 'Pendiente',
        'dedupe_hash' => hash('sha256', 'pending'),
        'assigned_at' => null,
    ]);
    UnassignedProcessAction::query()->create([
        'process_number' => '76364400300120260041000',
        'action' => 'Asignada',
        'dedupe_hash' => hash('sha256', 'assigned'),
        'assigned_at' => now(),
    ]);

    $results = UnassignedProcessAction::query()
        ->whereUnassigned()
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($pending->id);
});

it('filters by dedupe hash', function (): void {
    $hash = UnassignedProcessAction::makeDedupeHash(
        '76364400300120260040900',
        'Auto',
        null,
        '2026-08-03',
    );

    UnassignedProcessAction::query()->create([
        'process_number' => '76364400300120260040900',
        'action' => 'Auto',
        'registration_date' => '2026-08-03',
        'dedupe_hash' => $hash,
    ]);
    UnassignedProcessAction::query()->create([
        'process_number' => '76364400300120260040900',
        'action' => 'Otro',
        'dedupe_hash' => hash('sha256', 'other'),
    ]);

    $results = UnassignedProcessAction::query()
        ->whereDedupeHash($hash)
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->dedupe_hash)->toBe($hash);
});

it('orders by registration date ascending', function (): void {
    $newer = UnassignedProcessAction::query()->create([
        'process_number' => '76364400300120260040900',
        'action' => 'Nueva',
        'registration_date' => '2026-08-10',
        'dedupe_hash' => hash('sha256', 'newer'),
    ]);
    $older = UnassignedProcessAction::query()->create([
        'process_number' => '76364400300120260040900',
        'action' => 'Vieja',
        'registration_date' => '2026-08-01',
        'dedupe_hash' => hash('sha256', 'older'),
    ]);

    $results = UnassignedProcessAction::query()
        ->orderedByRegistrationDate()
        ->get();

    expect($results)->toHaveCount(2)
        ->and($results->first()->id)->toBe($older->id)
        ->and($results->last()->id)->toBe($newer->id);
});

<?php

declare(strict_types=1);

use Src\Application\Shared\Services\Process\SplitCombinedFijacionEstadoActionsService;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('splits combined fijacion estado actions and keeps the original row as estado', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76233408900120260014600',
    ]);

    $combined = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => -9001,
        'cons_action' => 3,
        'action' => 'Fijacion Estado Auto Admite Demanda',
        'annotation' => null,
        'registration_date' => '2026-04-24',
        'action_date' => '2026-04-24',
        'start_date' => '2026-04-27',
        'end_date' => '2026-04-27',
    ]);

    $result = app(SplitCombinedFijacionEstadoActionsService::class)->handle(
        ['76233408900120260014600'],
        false,
    );

    expect($result['split'])->toBe(1);

    $combined->refresh();
    expect($combined->action)->toBe('Fijación Estado');

    $decision = ProcessAction::query()
        ->where('process_id', $process->id)
        ->where('action', 'Auto Admite Demanda')
        ->first();

    expect($decision)->not->toBeNull()
        ->and($decision->cons_action)->toBe(4)
        ->and($decision->registration_date->format('Y-m-d'))->toBe('2026-04-24');
});

it('dry-run does not write changes', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76233408900120260015100',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => -9002,
        'cons_action' => 1,
        'action' => 'Fijacion Estado Auto Admite Demanda',
        'registration_date' => '2026-04-24',
        'action_date' => '2026-04-24',
    ]);

    $result = app(SplitCombinedFijacionEstadoActionsService::class)->handle(
        ['76233408900120260015100'],
        true,
    );

    expect($result['split'])->toBe(1)
        ->and(ProcessAction::query()->where('process_id', $process->id)->count())->toBe(1)
        ->and(ProcessAction::query()->where('process_id', $process->id)->value('action'))
        ->toBe('Fijacion Estado Auto Admite Demanda');
});

it('skips when decision part already exists', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76233408900120260014800',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => -9003,
        'cons_action' => 1,
        'action' => 'Fijacion Estado Auto Admite Demanda',
        'registration_date' => '2026-04-24',
        'action_date' => '2026-04-24',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => -9004,
        'cons_action' => 2,
        'action' => 'Auto Admite Demanda',
        'registration_date' => '2026-04-24',
        'action_date' => '2026-04-24',
    ]);

    $result = app(SplitCombinedFijacionEstadoActionsService::class)->handle(
        ['76233408900120260014800'],
        false,
    );

    expect($result['split'])->toBe(0)
        ->and($result['skipped_already_split'])->toBe(1);
});

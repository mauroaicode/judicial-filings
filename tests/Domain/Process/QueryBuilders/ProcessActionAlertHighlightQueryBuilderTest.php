<?php

declare(strict_types=1);

use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

beforeEach(function (): void {
    $this->action = ProcessAction::factory()->create();
});

it('filters highlights by process action', function (): void {
    ProcessActionAlertHighlight::query()->create([
        'process_action_id' => $this->action->id,
        'start' => 0,
        'end' => 8,
        'detected_text' => 'CONSULTA',
    ]);

    $otherAction = ProcessAction::factory()->create();
    ProcessActionAlertHighlight::query()->create([
        'process_action_id' => $otherAction->id,
        'start' => 0,
        'end' => 9,
        'detected_text' => 'APELACIÓN',
    ]);

    $results = ProcessActionAlertHighlight::query()
        ->whereProcessAction($this->action->id)
        ->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->detected_text)->toBe('CONSULTA');
});

it('orders highlights by start index', function (): void {
    ProcessActionAlertHighlight::query()->create([
        'process_action_id' => $this->action->id,
        'start' => 20,
        'end' => 29,
        'detected_text' => 'APELACIÓN',
    ]);
    ProcessActionAlertHighlight::query()->create([
        'process_action_id' => $this->action->id,
        'start' => 0,
        'end' => 8,
        'detected_text' => 'CONSULTA',
    ]);

    $results = ProcessActionAlertHighlight::query()
        ->whereProcessAction($this->action->id)
        ->orderedByStart()
        ->get();

    expect($results)->toHaveCount(2);
    expect($results->first()->start)->toBe(0);
    expect($results->last()->start)->toBe(20);
});

<?php

declare(strict_types=1);

use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('filters actions by process', function (): void {
    $process = Process::factory()->create();
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
    ]);

    $result = ProcessAction::query()
        ->whereProcess($process->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($action->id);
});

it('filters actions by action registration id', function (): void {
    $action = ProcessAction::factory()->create([
        'action_registration_id' => 123456,
    ]);

    $result = ProcessAction::query()
        ->whereActionRegistrationId(123456)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($action->id);
});

it('filters actions by process and registration id', function (): void {
    $process = Process::factory()->create();
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_registration_id' => 123456,
    ]);

    $result = ProcessAction::query()
        ->whereProcessAndRegistrationId($process->id, 123456)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($action->id);
});

it('orders actions by action date', function (): void {
    $process = Process::factory()->create();
    $action1 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => now()->subDay(),
    ]);
    $action2 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action_date' => now(),
    ]);

    $results = ProcessAction::query()
        ->whereProcess($process->id)
        ->orderedByActionDate()
        ->get();

    expect($results->first()->id)->toBe($action2->id);
    expect($results->last()->id)->toBe($action1->id);
});

it('orders actions by registration date', function (): void {
    $process = Process::factory()->create();
    $action1 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => now()->subDay(),
    ]);
    $action2 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'registration_date' => now(),
    ]);

    $results = ProcessAction::query()
        ->whereProcess($process->id)
        ->orderedByRegistrationDate()
        ->get();

    expect($results->first()->id)->toBe($action2->id);
    expect($results->last()->id)->toBe($action1->id);
});

it('orders actions by cons_action descending', function (): void {
    $process = Process::factory()->create();
    $action1 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'cons_action' => 10,
    ]);
    $action2 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'cons_action' => 20,
    ]);
    $action3 = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'cons_action' => 15,
    ]);

    $results = ProcessAction::query()
        ->whereProcess($process->id)
        ->orderedByConsActionDesc()
        ->get();

    expect($results->first()->id)->toBe($action2->id);
    expect($results->get(1)->id)->toBe($action3->id);
    expect($results->last()->id)->toBe($action1->id);
});

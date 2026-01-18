<?php

declare(strict_types=1);

use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

it('filters processes by process number', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001333301320170009301',
    ]);

    $result = Process::query()
        ->whereProcessNumber('76001333301320170009301')
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($process->id);
});

it('filters processes by process id', function (): void {
    $process = Process::factory()->create([
        'process_id' => 1234567890,
    ]);

    $result = Process::query()
        ->whereProcessId(1234567890)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($process->id);
});

it('filters processes by organization', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();

    $process->organizations()->attach($organization->id, [
        'interest_date' => now(),
        'is_active' => true,
    ]);

    $result = Process::query()
        ->whereOrganization($organization->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($process->id);
});

it('orders processes by created_at', function (): void {
    $process1 = Process::factory()->create(['created_at' => now()->subDay()]);
    $process2 = Process::factory()->create(['created_at' => now()]);

    $results = Process::query()
        ->orderedByCreatedAt()
        ->get();

    expect($results->first()->id)->toBe($process2->id);
    expect($results->last()->id)->toBe($process1->id);
});

it('orders processes by process_date', function (): void {
    $process1 = Process::factory()->create(['process_date' => now()->subDay()]);
    $process2 = Process::factory()->create(['process_date' => now()]);

    $results = Process::query()
        ->orderedByProcessDate()
        ->get();

    expect($results->first()->id)->toBe($process2->id);
    expect($results->last()->id)->toBe($process1->id);
});

it('includes actions relationship', function (): void {
    $process = Process::factory()->create();

    $result = Process::query()
        ->withActions()
        ->find($process->id);

    expect($result->relationLoaded('actions'))->toBeTrue();
});

it('includes subjects relationship', function (): void {
    $process = Process::factory()->create();

    $result = Process::query()
        ->withSubjects()
        ->find($process->id);

    expect($result->relationLoaded('subjects'))->toBeTrue();
});

it('includes all relationships', function (): void {
    $process = Process::factory()->create();

    $result = Process::query()
        ->withRelations()
        ->find($process->id);

    expect($result->relationLoaded('actions'))->toBeTrue();
    expect($result->relationLoaded('subjects'))->toBeTrue();
    expect($result->relationLoaded('organizations'))->toBeTrue();
});

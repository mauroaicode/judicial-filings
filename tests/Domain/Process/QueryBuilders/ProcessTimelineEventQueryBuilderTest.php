<?php

declare(strict_types=1);

use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;

it('filters timeline events by process', function (): void {
    $process = Process::factory()->create();
    $otherProcess = Process::factory()->create();
    $event = ProcessTimelineEvent::factory()->create([
        'process_id' => $process->id,
        'process_number' => $process->process_number,
    ]);
    ProcessTimelineEvent::factory()->create([
        'process_id' => $otherProcess->id,
        'process_number' => $otherProcess->process_number,
    ]);

    $events = ProcessTimelineEvent::query()->forProcess($process->id)->get();

    expect($events)->toHaveCount(1);
    expect($events->first()->id)->toBe($event->id);
});

it('returns global and own organization events without leaking another tenant', function (): void {
    $process = Process::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $global = ProcessTimelineEvent::factory()->create([
        'process_id' => $process->id,
        'process_number' => $process->process_number,
        'organization_id' => null,
    ]);
    $own = ProcessTimelineEvent::factory()->create([
        'process_id' => $process->id,
        'process_number' => $process->process_number,
        'organization_id' => $organization->id,
    ]);
    $other = ProcessTimelineEvent::factory()->create([
        'process_id' => $process->id,
        'process_number' => $process->process_number,
        'organization_id' => $otherOrganization->id,
    ]);

    $ids = ProcessTimelineEvent::query()
        ->visibleToOrganization($organization->id)
        ->pluck('id');

    expect($ids)->toContain($global->id, $own->id);
    expect($ids)->not->toContain($other->id);
});

it('orders timeline events by occurrence and recording dates descending', function (): void {
    $process = Process::factory()->create();
    $older = ProcessTimelineEvent::factory()->create([
        'process_id' => $process->id,
        'process_number' => $process->process_number,
        'occurred_at' => now()->subDay(),
        'recorded_at' => now()->subDay(),
    ]);
    $newer = ProcessTimelineEvent::factory()->create([
        'process_id' => $process->id,
        'process_number' => $process->process_number,
        'occurred_at' => now(),
        'recorded_at' => now(),
    ]);

    $events = ProcessTimelineEvent::query()->latestFirst()->get();

    expect($events->first()->id)->toBe($newer->id);
    expect($events->last()->id)->toBe($older->id);
});

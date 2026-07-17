<?php

declare(strict_types=1);

use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Application\Shared\Process\Timeline\Resources\ProcessTimelineEventResource;
use Src\Application\Shared\Process\Timeline\Services\BackfillProcessTimelineService;
use Src\Application\Shared\Process\Timeline\Services\RecordSemaphoreTimelineEventService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;
use Src\Domain\Task\Models\Task;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->otherOrganization = Organization::factory()->create();
    $this->user = AppUser::factory()->create();
    $this->user->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->process = Process::factory()->create();
    $this->process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);
    $this->process->organizations()->attach($this->otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);
});

it('returns global events and only events for the authenticated organization', function (): void {
    $global = ProcessTimelineEvent::factory()->create([
        'process_id' => $this->process->id,
        'process_number' => $this->process->process_number,
        'organization_id' => null,
        'idempotency_key' => 'timeline-test-global',
    ]);
    $own = ProcessTimelineEvent::factory()->create([
        'process_id' => $this->process->id,
        'process_number' => $this->process->process_number,
        'organization_id' => $this->organization->id,
        'idempotency_key' => 'timeline-test-own',
    ]);
    $other = ProcessTimelineEvent::factory()->create([
        'process_id' => $this->process->id,
        'process_number' => $this->process->process_number,
        'organization_id' => $this->otherOrganization->id,
        'idempotency_key' => 'timeline-test-other',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$this->process->id}/timeline");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)
        ->toContain($global->id, $own->id)
        ->not->toContain($other->id);
});

it('does not expose a timeline for a process outside the organization', function (): void {
    $unrelatedProcess = Process::factory()->create();

    $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$unrelatedProcess->id}/timeline")
        ->assertNotFound();
});

it('returns user-facing translations without replacing technical values', function (): void {
    app()->setLocale('es');

    ProcessTimelineEvent::factory()->create([
        'process_id' => $this->process->id,
        'process_number' => $this->process->process_number,
        'organization_id' => $this->organization->id,
        'event_type' => ProcessTimelineEventType::SEMAPHORE_CHANGED,
        'source' => ProcessTimelineEventSource::BACKFILL,
        'payload' => [
            'from' => null,
            'to' => 'green',
            'lawyer_role' => 'plaintiff',
            'reason' => 'current_state_backfill',
        ],
        'is_backfilled' => true,
        'occurred_at_is_estimated' => true,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/app-user/processes/{$this->process->id}/timeline")
        ->assertOk()
        ->assertJsonPath('data.0.payload.lawyer_role', 'plaintiff')
        ->assertJsonPath('data.0.payload.reason', 'current_state_backfill')
        ->assertJsonPath('data.0.display.title', 'Cambió el semáforo')
        ->assertJsonPath('data.0.display.summary', 'El semáforo cambió de Sin nivel anterior a Verde.')
        ->assertJsonPath('data.0.display.reason', 'Estado inicial registrado')
        ->assertJsonPath('data.0.display.role', 'Demandante')
        ->assertJsonPath('data.0.display.show_technical_metadata', false);
});

it('returns translated task values and a twelve-hour time', function (): void {
    app()->setLocale('es');

    $created = ProcessTimelineEvent::factory()->create([
        'process_id' => $this->process->id,
        'process_number' => $this->process->process_number,
        'organization_id' => $this->organization->id,
        'event_type' => ProcessTimelineEventType::TASK_CREATED,
        'payload' => [
            'type' => 'suspension',
            'status' => 'pending',
            'title' => 'Suspende por vencimiento de términos',
        ],
        'occurred_at' => '2026-07-13 17:25:00',
    ]);
    $statusChanged = ProcessTimelineEvent::factory()->create([
        'process_id' => $this->process->id,
        'process_number' => $this->process->process_number,
        'organization_id' => $this->organization->id,
        'event_type' => ProcessTimelineEventType::TASK_STATUS_CHANGED,
        'payload' => [
            'from' => null,
            'to' => 'completed',
            'task_type' => 'suspension',
        ],
    ]);

    $createdDisplay = ProcessTimelineEventResource::fromModel($created)->toArray()['display'];
    $statusDisplay = ProcessTimelineEventResource::fromModel($statusChanged)->toArray()['display'];

    expect($createdDisplay)
        ->task_type->toBe('Suspensión')
        ->task_status->toBe('Pendiente')
        ->summary->toBe('Suspende por vencimiento de términos')
        ->time->toBe('5:25 PM')
        ->and($statusDisplay)
        ->from->toBe('Sin información')
        ->to->toBe('Cumplida')
        ->summary->toBe('El estado cambió de Sin información a Cumplida.');
});

it('records an idempotent event only once', function (): void {
    $recorder = app(ProcessTimelineRecorder::class);
    $data = new RecordProcessTimelineEventData(
        eventType: ProcessTimelineEventType::PROCESS_BECAME_PRIVATE,
        source: ProcessTimelineEventSource::JUDICIAL_BRANCH,
        idempotencyKey: 'privacy-idempotency-test',
        payload: ['from' => false, 'to' => true],
    );

    $first = $recorder->handle($this->process, $data);
    $second = $recorder->handle($this->process, $data);

    expect($second->id)->toBe($first->id);
    expect(ProcessTimelineEvent::query()
        ->where('idempotency_key', 'privacy-idempotency-test')
        ->count())->toBe(1);
});

it('backfills existing data safely and can be rerun', function (): void {
    $this->process->update([
        'became_private_at' => now()->subDay(),
        'is_private' => true,
    ]);
    Task::factory()->create([
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
    ]);

    $service = app(BackfillProcessTimelineService::class);
    $simulation = $service->handle(dryRun: true, chunkSize: 10);

    expect($simulation['created'])->toBeGreaterThan(0);
    expect(ProcessTimelineEvent::query()
        ->where('process_id', $this->process->id)
        ->count())->toBe(0);

    $service->handle(chunkSize: 10);
    $countAfterFirstRun = ProcessTimelineEvent::query()
        ->where('process_id', $this->process->id)
        ->count();
    $service->handle(chunkSize: 10);

    expect($countAfterFirstRun)->toBeGreaterThan(0);
    expect(ProcessTimelineEvent::query()
        ->where('process_id', $this->process->id)
        ->count())->toBe($countAfterFirstRun);
});

it('records the effective semaphore color after a judicial action reset', function (): void {
    app(RecordSemaphoreTimelineEventService::class)->handle(
        process: $this->process,
        organizationId: $this->organization->id,
        from: 'yellow',
        to: 'green',
        reason: 'new_judicial_action',
        lawyerRole: 'plaintiff',
        source: ProcessTimelineEventSource::JUDICIAL_BRANCH,
        subjectType: 'process_action',
        subjectId: 'action-001',
    );

    $event = ProcessTimelineEvent::query()
        ->where('process_id', $this->process->id)
        ->where('event_type', ProcessTimelineEventType::SEMAPHORE_CHANGED->value)
        ->where('subject_id', 'action-001')
        ->firstOrFail();

    expect($event->payload)->toMatchArray([
        'from' => 'yellow',
        'to' => 'green',
        'stored_level_after_reset' => null,
    ]);
});

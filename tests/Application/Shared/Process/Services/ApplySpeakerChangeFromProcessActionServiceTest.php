<?php

declare(strict_types=1);

use Src\Application\Shared\Process\Services\ApplySpeakerChangeFromProcessActionService;
use Src\Application\Shared\Process\Services\BackfillSpeakerFromProcessActionsService;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessTimelineEvent;

it('updates speaker and records timeline from a cambio de ponente action', function (): void {
    $process = Process::factory()->create([
        'speaker' => 'FERNANDO AUGUSTO GARCIA MUÑOZ (E)',
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Cambio de ponente',
        'annotation' => 'OUT-Nuevo titular del despacho.-Ponente anterior: FERNANDO AUGUSTO GARCIA MUÑOZ (E) Ponente nuevo:JOHN ALEXANDER HURTADO PAREDES',
        'action_date' => '2026-09-25',
        'registration_date' => '2026-09-25',
    ]);

    $updated = app(ApplySpeakerChangeFromProcessActionService::class)->handle(
        $process,
        $action,
        ProcessTimelineEventSource::JUDICIAL_BRANCH,
    );

    $process->refresh();

    expect($updated)->toBeTrue()
        ->and($process->speaker)->toBe('JOHN ALEXANDER HURTADO PAREDES');

    $event = ProcessTimelineEvent::query()
        ->where('process_id', $process->id)
        ->where('event_type', ProcessTimelineEventType::SPEAKER_CHANGED->value)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['from'])->toBe('FERNANDO AUGUSTO GARCIA MUÑOZ (E)')
        ->and($event->payload['to'])->toBe('JOHN ALEXANDER HURTADO PAREDES')
        ->and($event->payload['reason'])->toBe('speaker_updated_from_action')
        ->and($event->subject_type)->toBe('process_action')
        ->and($event->subject_id)->toBe($action->id);
});

it('does not update when speaker already matches ponente nuevo', function (): void {
    $process = Process::factory()->create([
        'speaker' => 'JOHN ALEXANDER HURTADO PAREDES',
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Cambio de ponente',
        'annotation' => 'Ponente anterior: FERNANDO AUGUSTO GARCIA MUÑOZ (E) Ponente nuevo:JOHN ALEXANDER HURTADO PAREDES',
    ]);

    $updated = app(ApplySpeakerChangeFromProcessActionService::class)->handle($process, $action);

    expect($updated)->toBeFalse()
        ->and(ProcessTimelineEvent::query()->where('process_id', $process->id)->count())->toBe(0);
});

it('backfills speaker from the latest cambio de ponente action', function (): void {
    $process = Process::factory()->create([
        'speaker' => 'FERNANDO AUGUSTO GARCIA MUÑOZ (E)',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Cambio de ponente',
        'annotation' => 'Ponente anterior: OLD ONE Ponente nuevo:SOMEONE ELSE',
        'action_date' => '2026-08-01',
        'registration_date' => '2026-08-01',
    ]);
    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Cambio de ponente',
        'annotation' => 'Ponente anterior: FERNANDO AUGUSTO GARCIA MUÑOZ (E) Ponente nuevo:JOHN ALEXANDER HURTADO PAREDES',
        'action_date' => '2026-09-25',
        'registration_date' => '2026-09-25',
    ]);

    $counts = app(BackfillSpeakerFromProcessActionsService::class)->handle();

    $process->refresh();

    expect($counts['updated'])->toBeGreaterThan(0)
        ->and($process->speaker)->toBe('JOHN ALEXANDER HURTADO PAREDES');
});

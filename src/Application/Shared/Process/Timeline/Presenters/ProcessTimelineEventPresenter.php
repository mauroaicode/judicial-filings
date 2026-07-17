<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Presenters;

use Illuminate\Support\Facades\Lang;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\ProcessTimelineEvent;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;

final class ProcessTimelineEventPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function for(ProcessTimelineEvent $event): array
    {
        $payload = $event->payload;
        $eventType = $event->event_type->value;
        $isSemaphore = $event->event_type === ProcessTimelineEventType::SEMAPHORE_CHANGED;
        $isTaskStatus = $event->event_type === ProcessTimelineEventType::TASK_STATUS_CHANGED;
        $fromLabel = match (true) {
            $isSemaphore => self::colorLabel($payload['from'] ?? null),
            $isTaskStatus => self::taskStatusLabel($payload['from'] ?? null, true),
            default => null,
        };
        $toLabel = match (true) {
            $isSemaphore => self::colorLabel($payload['to'] ?? null),
            $isTaskStatus => self::taskStatusLabel($payload['to'] ?? null),
            default => null,
        };

        return [
            'title' => self::translate('event_types', $eventType, 'event_types.unknown'),
            'summary' => self::summary($event->event_type, $payload, $fromLabel, $toLabel),
            'reason' => self::reason($payload['reason'] ?? null),
            'role' => self::role($payload['lawyer_role'] ?? null),
            'from' => $fromLabel,
            'to' => $toLabel,
            'task_type' => self::taskTypeLabel($payload['type'] ?? $payload['task_type'] ?? null),
            'task_status' => self::taskStatusLabel($payload['status'] ?? null),
            'source' => self::translate('sources', $event->source->value, 'sources.system'),
            'actor' => self::translate('actors', $event->actor_type, 'actors.unknown'),
            'time' => $event->occurred_at
                ->copy()
                ->timezone(config('app.timezone'))
                ->format('g:i A'),
            'show_technical_metadata' => false,
        ];
    }

    private static function reason(mixed $reason): ?string
    {
        if (! is_string($reason) || $reason === '') {
            return null;
        }

        return self::translate('reasons', $reason, 'reasons.unknown');
    }

    private static function role(mixed $role): ?string
    {
        if (! is_string($role)) {
            return null;
        }

        return ProcessLawyerRole::tryFrom($role)?->getLabel();
    }

    private static function colorLabel(mixed $color): ?string
    {
        if ($color === null) {
            return __('process_timeline.colors.none');
        }

        if (! is_string($color) || ! Lang::has("process_timeline.colors.{$color}")) {
            return null;
        }

        return __("process_timeline.colors.{$color}");
    }

    private static function taskStatusLabel(mixed $status, bool $showNotAvailable = false): ?string
    {
        if ($status === null) {
            return $showNotAvailable ? __('process_timeline.values.not_available') : null;
        }

        return is_string($status) ? TaskStatus::tryFrom($status)?->getLabel() : null;
    }

    private static function taskTypeLabel(mixed $type): ?string
    {
        return is_string($type) ? TaskType::tryFrom($type)?->getLabel() : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function summary(
        ProcessTimelineEventType $eventType,
        array $payload,
        ?string $from,
        ?string $to,
    ): ?string {
        if ($from !== null && $to !== null) {
            if ($eventType === ProcessTimelineEventType::SEMAPHORE_CHANGED) {
                return __('process_timeline.summaries.semaphore_changed', [
                    'from' => $from,
                    'to' => $to,
                ]);
            }

            if ($eventType === ProcessTimelineEventType::TASK_STATUS_CHANGED) {
                return __('process_timeline.summaries.task_status_changed', [
                    'from' => $from,
                    'to' => $to,
                ]);
            }
        }

        if ($eventType === ProcessTimelineEventType::TASK_CREATED && is_string($payload['title'] ?? null)) {
            return $payload['title'];
        }

        return null;
    }

    private static function translate(string $group, ?string $value, string $fallback): string
    {
        $key = "process_timeline.{$group}.{$value}";

        return Lang::has($key) ? __($key) : __("process_timeline.{$fallback}");
    }
}

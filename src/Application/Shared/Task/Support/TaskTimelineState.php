<?php

declare(strict_types=1);

namespace Src\Application\Shared\Task\Support;

use BackedEnum;
use DateTimeInterface;
use Src\Domain\Task\Models\Task;

final class TaskTimelineState
{
    private const TRACKED_ATTRIBUTES = [
        'title',
        'description',
        'type',
        'due_date',
        'reminder_days',
        'status',
        'process_id',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function capture(Task $task): array
    {
        $state = [];

        foreach (self::TRACKED_ATTRIBUTES as $attribute) {
            $state[$attribute] = self::normalize($task->getAttribute($attribute));
        }

        return $state;
    }

    public static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => $value,
        };
    }
}

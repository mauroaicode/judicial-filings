<?php

declare(strict_types=1);

namespace Src\Domain\Task\Enums;

enum TaskUrgencyLevel: string
{
    case NORMAL = 'normal';
    case ALERT_1 = 'alert_1';
    case ALERT_2 = 'alert_2';
    case CRITICAL = 'critical';

    /**
     * Numeric rank for comparing escalation (higher = more urgent).
     */
    public function rank(): int
    {
        return match ($this) {
            self::NORMAL => 0,
            self::ALERT_1 => 1,
            self::ALERT_2 => 2,
            self::CRITICAL => 3,
        };
    }

    public function getLabel(): string
    {
        return __('enums.task_urgency_level.'.$this->value);
    }

    public function notificationType(): string
    {
        return match ($this) {
            self::ALERT_1 => 'task_urgency_alert_1',
            self::ALERT_2 => 'task_urgency_alert_2',
            self::CRITICAL => 'task_urgency_critical',
            self::NORMAL => 'task_urgency_normal',
        };
    }

    public function isNotifiable(): bool
    {
        return $this !== self::NORMAL;
    }

    /**
     * @return array<string>
     */
    public static function notifiableValues(): array
    {
        return array_map(
            fn (self $level): string => $level->value,
            array_filter(self::cases(), fn (self $level): bool => $level->isNotifiable()),
        );
    }
}

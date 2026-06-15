<?php

declare(strict_types=1);

namespace Src\Domain\Task\Enums;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case DRAFT = 'draft';

    /**
     * Get the label for the status.
     */
    public function getLabel(): string
    {
        return __('enums.task_status.'.$this->value);
    }

    /**
     * Get all cases as array for API listing.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->getLabel(),
            ],
            self::cases()
        );
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

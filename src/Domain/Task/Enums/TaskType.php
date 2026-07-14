<?php

declare(strict_types=1);

namespace Src\Domain\Task\Enums;

enum TaskType: string
{
    case GENERAL = 'general';
    case SUSPENSION = 'suspension';

    /**
     * Get the label for the type.
     */
    public function getLabel(): string
    {
        return __('enums.task_type.'.$this->value);
    }

    /**
     * Get all cases as array for API listing.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->getLabel(),
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

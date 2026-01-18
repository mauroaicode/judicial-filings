<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

enum ProcessStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case CLOSED = 'closed';
    case PENDING = 'pending';

    /**
     * Get the label for the status.
     */
    public function getLabel(): string
    {
        return __('enums.process_status.'.$this->value);
    }

    /**
     * Get all status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

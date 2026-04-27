<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

enum ProcessImportBatchStatus: string
{
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * Get the human-readable label for this status.
     */
    public function getLabel(): string
    {
        return __('enums.process_import_batch_status.'.$this->value);
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

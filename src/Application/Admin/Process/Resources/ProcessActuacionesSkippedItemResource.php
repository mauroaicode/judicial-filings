<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;

/**
 * One actuación omitted during Excel import (typically a duplicate).
 */
class ProcessActuacionesSkippedItemResource extends Resource
{
    public function __construct(
        public string $process_number,
        public string $action,
        public ?string $annotation,
        public ?string $registration_date,
        public ?string $court,
        public int $excel_row,
        public string $reason,
    ) {}
}

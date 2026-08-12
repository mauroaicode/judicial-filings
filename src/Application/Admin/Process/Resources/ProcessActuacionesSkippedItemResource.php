<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;

/**
 * One actuación omitted during Excel import (typically a duplicate).
 *
 * {@see $action} is the Excel actuación text (or the meaningful part that was
 * skipped). {@see $message} is a localized explanation for the admin UI.
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
        public string $message = '',
    ) {}
}

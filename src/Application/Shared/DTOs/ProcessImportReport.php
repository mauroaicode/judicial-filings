<?php

declare(strict_types=1);

namespace Src\Application\Shared\DTOs;

use Illuminate\Support\Carbon;

readonly class ProcessImportReport
{
    /**
     * @param  array<int, array{process_number: string, reason: string}>  $errors
     * @param  string|null  $reportRecipientEmail  Email destino si no existe PROCESS_IMPORT_REPORT_EMAIL (ej. usuario que solicitó la importación).
     */
    public function __construct(
        public string $batchId,
        public string $organizationName,
        public int $totalCount,
        public int $successCount,
        public int $failedCount,
        public array $errors,
        public ?Carbon $completedAt,
        public ?string $reportRecipientEmail = null,
    ) {}
}

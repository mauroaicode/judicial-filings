<?php

declare(strict_types=1);

namespace Src\Application\Shared\DTOs;

use Illuminate\Support\Carbon;

/**
 * Carries the completed import batch summary used for email reports, logs and notifications.
 *
 * Note on counts:
 *  - excelTotalCount  : valid radicados present in the uploaded file (enqueued + skipped).
 *  - totalCount       : radicados actually enqueued (after filtering already-registered for this org).
 *  - successCount     : judicial instances registered; may exceed totalCount when radicados
 *                       have multiple instances (doble instancia).
 *  - multipleInstancesCount : radicados (not instances) that had more than one judicial instance.
 */
readonly class ProcessImportReport
{
    /**
     * @param  string  $batchId  Import batch UUID
     * @param  string  $organizationName  Organization display name
     * @param  int  $excelTotalCount  Total valid radicados in the uploaded Excel
     * @param  int  $totalCount  Radicados enqueued (after skipping already-registered for this org)
     * @param  int  $multipleInstancesCount  Radicados with more than one judicial instance
     * @param  int  $successCount  Judicial instances successfully registered
     * @param  int  $failedCount  Radicados that could not be registered
     * @param  array<int, array{process_number: string, reason: string}>  $errors  Failed radicado details
     * @param  Carbon|null  $completedAt  Batch completion timestamp
     * @param  string|null  $reportRecipientEmail  Fallback email when admin_report_email is not configured
     */
    public function __construct(
        public string $batchId,
        public string $organizationName,
        public int $excelTotalCount,
        public int $totalCount,
        public int $multipleInstancesCount,
        public int $successCount,
        public int $failedCount,
        public array $errors,
        public ?Carbon $completedAt,
        public ?string $reportRecipientEmail = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\DTOs\ProcessImportReport;

/**
 * Internal import-report driver — stores the notification event for future WebSocket delivery.
 *
 * Until WebSocket is implemented this driver logs the event for audit purposes.
 * Called directly by ImportReportNotificationService (does not need a channel model).
 */
class InternalImportReportChannelDriver
{
    /**
     * Records the import report notification for the given context (admin|organization:<id>).
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  string  $context  Identifies the recipient context
     */
    public function send(ProcessImportReport $report, string $context): void
    {
        // TODO: emit WebSocket event when real-time layer is ready.
        $this->log('Internal import report notification recorded', $report, $context);
    }

    /**
     * Writes the notification log entry.
     *
     * @param  string  $message  Log message
     * @param  ProcessImportReport  $report  Report DTO
     * @param  string  $context  Recipient context
     */
    private function log(string $message, ProcessImportReport $report, string $context): void
    {
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->info($message, [
                'batch_id' => $report->batchId,
                'organization' => $report->organizationName,
                'total' => $report->totalCount,
                'success' => $report->successCount,
                'failed' => $report->failedCount,
                'context' => $context,
            ]);
    }
}

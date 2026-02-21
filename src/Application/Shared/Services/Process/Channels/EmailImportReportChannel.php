<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Application\Shared\Contracts\Process\ImportReportChannelInterface;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Application\Shared\Mail\ProcessImportReportMailable;

class EmailImportReportChannel implements ImportReportChannelInterface
{
    public function send(ProcessImportReport $report): void
    {
        $to = config('process-import.admin_report_email');
        if (empty($to) || ! is_string($to)) {
            $to = $report->reportRecipientEmail;
        }
        if (empty($to) || ! is_string($to)) {
            Log::channel(config('process-import.log_channel', 'process_import'))
                ->warning('Process import report email skipped: PROCESS_IMPORT_REPORT_EMAIL not set and no requested_by user email');

            return;
        }

        try {
            Mail::to($to)->send(new ProcessImportReportMailable($report));
        } catch (\Throwable $e) {
            Log::channel(config('process-import.log_channel', 'process_import'))
                ->error('Process import report email failed', [
                    'batch_id' => $report->batchId,
                    'message' => $e->getMessage(),
                ]);
            throw $e;
        }
    }
}

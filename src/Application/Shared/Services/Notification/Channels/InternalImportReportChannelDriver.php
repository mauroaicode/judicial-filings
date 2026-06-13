<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Application\Shared\Notifications\ProcessImportFinishedNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessImportBatch;

/**
 * Internal import-report driver — dispatches the in-app bell notification to organization users.
 *
 * Admin context (`admin`) is audit-logged only — there is no admin in-app notifiable.
 * Organization context (`organization:<uuid>`) notifies all app users, same entry point as admin.
 */
class InternalImportReportChannelDriver
{
    private const ORGANIZATION_CONTEXT_PREFIX = 'organization:';

    /**
     * Records or dispatches the import report notification for the given context.
     *
     * @param  ProcessImportReport  $report  Completed import report
     * @param  string  $context  `admin` or `organization:<organization-uuid>`
     */
    public function send(ProcessImportReport $report, string $context): void
    {
        if (! str_starts_with($context, self::ORGANIZATION_CONTEXT_PREFIX)) {
            $this->log('Internal import report notification recorded', $report, $context);

            return;
        }

        $organizationId = substr($context, strlen(self::ORGANIZATION_CONTEXT_PREFIX));
        $batch = ProcessImportBatch::query()->find($report->batchId);

        if ($batch === null) {
            throw new \RuntimeException("Import batch not found: {$report->batchId}");
        }

        $organization = Organization::query()
            ->with('appUsers')
            ->find($organizationId);

        if ($organization === null || $organization->appUsers->isEmpty()) {
            $this->log('Internal import report notification skipped (no app users)', $report, $context);

            return;
        }

        Notification::send(
            $organization->appUsers,
            new ProcessImportFinishedNotification($batch),
        );

        $this->log('Internal import report notification dispatched', $report, $context, [
            'user_count' => $organization->appUsers->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(string $message, ProcessImportReport $report, string $context, array $extra = []): void
    {
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->info($message, array_merge([
                'batch_id' => $report->batchId,
                'organization' => $report->organizationName,
                'total' => $report->totalCount,
                'success' => $report->successCount,
                'failed' => $report->failedCount,
                'context' => $context,
            ], $extra));
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Src\Application\Admin\Process\DTOs\ProcessImportDataResult;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Application\Shared\Jobs\ImportRadicadoJob;
use Src\Application\Shared\Notifications\ProcessImportReportNotification;
use Src\Domain\Process\Models\ProcessImportBatch;

readonly class ProcessImportBatchService
{
    /**
     * Crea el batch, encola jobs y despacha. Solo debe llamarse cuando $data->isReadyToEnqueue().
     *
     * @return array{message: string, batch_id: string, skipped_already_registered?: int}
     * @throws \Throwable
     */
    public function dispatch(ProcessImportDataResult $data): array
    {
        $toEnqueue = $data->toEnqueue;
        $organizationId = $data->organizationId;
        $fileName = $data->fileName;
        $requestedById = $data->requestedById;

        $batch = ProcessImportBatch::query()->create([
            'organization_id' => $organizationId,
            'requested_by' => $requestedById,
            'file_name' => $fileName,
            'total_count' => count($toEnqueue),
            'enqueued_process_numbers' => $toEnqueue,
            'status' => ProcessImportBatch::STATUS_PROCESSING,
        ]);

        $delaySeconds = (int) config('process-import.delay_between_radicados_seconds');
        $jobs = [];
        $importRadicadoQueue = config('process-import.jobs.import_radicado.queue');
        foreach ($toEnqueue as $index => $processNumber) {
            $job = (new ImportRadicadoJob(
                $batch->id,
                $processNumber,
                $organizationId,
            ))->onQueue($importRadicadoQueue)->delay(now()->addSeconds($index * $delaySeconds));
            $jobs[] = $job;
        }

        $queueName = config('process-import.queue') ?: 'process-import';

        $laravelBatch = Bus::batch($jobs)
            ->allowFailures()
            ->onQueue($queueName)
            ->then(function (Batch $batch) use ($queueName): void {
                $this->onBatchCompleted($batch->id, $queueName);
            })
            ->dispatch();

        $batch->update(['laravel_batch_id' => $laravelBatch->id]);

        $logChannel = config('process-import.log_channel', 'process_import');
        Log::channel($logChannel)->info('Import batch dispatched', [
            'batch_id' => $batch->id,
            'laravel_batch_id' => $laravelBatch->id,
            'total_jobs' => count($toEnqueue),
            'queue' => $queueName,
        ]);

        $body = [
            'message' => __('process.import_started'),
            'batch_id' => $batch->id,
        ];
        if ($data->skippedAlreadyRegistered > 0) {
            $body['skipped_already_registered'] = $data->skippedAlreadyRegistered;
        }

        return $body;
    }

    private function onBatchCompleted(string $laravelBatchId, string $queueName): void
    {
        $importBatch = ProcessImportBatch::query()
            ->with('organization', 'requestedByUser')
            ->where('laravel_batch_id', $laravelBatchId)
            ->first();

        if (! $importBatch) {
            return;
        }

        $importBatch->update([
            'status' => ProcessImportBatch::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $logChannel = config('process-import.log_channel', 'process_import');
        Log::channel($logChannel)->info('Import batch completed', [
            'batch_id' => $importBatch->id,
            'total_count' => $importBatch->total_count,
            'success_count' => $importBatch->success_count,
            'failed_count' => $importBatch->failed_count,
            'errors_sample' => array_slice($importBatch->errors ?? [], 0, 5),
        ]);

        $report = new ProcessImportReport(
            batchId: $importBatch->id,
            organizationName: $importBatch->organization?->name ?? '',
            totalCount: $importBatch->total_count,
            successCount: $importBatch->success_count,
            failedCount: $importBatch->failed_count,
            errors: $importBatch->errors ?? [],
            completedAt: $importBatch->completed_at,
            reportRecipientEmail: $importBatch->requestedByUser?->email,
        );

        $to = config('process-import.report_email');
        if (empty($to) || ! is_string($to)) {
            $to = $report->reportRecipientEmail;
        }
        if (! empty($to) && is_string($to)) {
            Notification::route('mail', $to)->notify(new ProcessImportReportNotification($report));
            Log::channel($logChannel)->info('Import report notification queued', [
                'batch_id' => $importBatch->id,
                'queue' => 'emails_import_report',
            ]);
        }
    }
}

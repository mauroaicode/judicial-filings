<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Random\RandomException;
use Src\Application\Admin\Process\DTOs\ProcessImportDataResult;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Application\Shared\Jobs\ImportRadicadoJob;
use Src\Application\Shared\Notifications\ProcessImportFinishedNotification;
use Src\Application\Shared\Services\Notification\ImportReportNotificationService;
use Src\Domain\Process\Models\ProcessImportBatch;
use Throwable;

readonly class ProcessImportBatchService
{
    public function __construct(
        private ImportReportNotificationService $notificationService,
    ) {}

    /**
     * Creates the batch record, builds staggered jobs and dispatches the Laravel batch.
     *
     * @param  ProcessImportDataResult  $data  Validated import data ready to enqueue
     * @return array{message: string, batch_id: string, skipped_already_registered?: int}
     *
     * @throws Throwable
     */
    public function dispatch(ProcessImportDataResult $data): array
    {
        $batch = $this->createBatchRecord($data);
        $jobs = $this->buildJobs($data->toEnqueue, $data->organizationId, $batch->id);
        $queueName = config('process-import.jobs.import_radicado.queue');

        $laravelBatch = Bus::batch($jobs)
            ->allowFailures()
            ->onQueue($queueName)
            ->then(fn (Batch $b) => $this->onBatchCompleted($b->id))
            ->dispatch();

        $batch->update(['laravel_batch_id' => $laravelBatch->id]);

        $this->log('Import batch dispatched', [
            'batch_id' => $batch->id,
            'laravel_batch_id' => $laravelBatch->id,
            'total_jobs' => count($data->toEnqueue),
            'queue' => $queueName,
        ]);

        return $this->buildResponse($batch, $data->skippedAlreadyRegistered);
    }

    /**
     * Marks batch as completed, builds the report and dispatches notifications.
     *
     * @param  string  $laravelBatchId  Laravel batch identifier
     */
    private function onBatchCompleted(string $laravelBatchId): void
    {
        $importBatch = ProcessImportBatch::query()
            ->with('organization', 'requestedByUser')
            ->where('laravel_batch_id', $laravelBatchId)
            ->first();

        if (! $importBatch) {
            return;
        }

        $this->markBatchCompleted($importBatch);

        $report = $this->buildReport($importBatch);

        $this->notificationService->notifyAdmin($report);
        $this->notificationService->notifyOrganization($report, $importBatch->organization_id);

        if ($importBatch->requestedByUser) {
            $importBatch->requestedByUser->notify(new ProcessImportFinishedNotification($importBatch));
        }

        $importBatch->organization->appUsers->each(function ($appUser) use ($importBatch): void {
            $appUser->notify(new ProcessImportFinishedNotification($importBatch));
        });
    }

    /**
     * Persists the initial batch record with PROCESSING status.
     *
     * @param  ProcessImportDataResult  $data  Validated import data
     */
    private function createBatchRecord(ProcessImportDataResult $data): ProcessImportBatch
    {
        return ProcessImportBatch::query()->create([
            'organization_id' => $data->organizationId,
            'requested_by' => $data->requestedById,
            'file_name' => $data->fileName,
            'excel_total_count' => count($data->toEnqueue) + $data->skippedAlreadyRegistered,
            'total_count' => count($data->toEnqueue),
            'enqueued_process_numbers' => $data->toEnqueue,
            'status' => ProcessImportBatch::STATUS_PROCESSING,
        ]);
    }

    /**
     * Builds the ImportRadicadoJob array with staggered delays per index.
     *
     * @param  array<int, string>  $toEnqueue  Process numbers to enqueue
     * @param  string  $organizationId  Organization identifier
     * @param  string  $batchId  Import batch DB identifier
     * @return array<int, ImportRadicadoJob>
     */
    private function buildJobs(array $toEnqueue, string $organizationId, string $batchId): array
    {
        $queue = config('process-import.jobs.import_radicado.queue');
        $jobs = [];
        $accumulatedDelay = 0;

        foreach ($toEnqueue as $processNumber) {
            $accumulatedDelay += $this->resolveRandomDelaySeconds();

            $jobs[] = (new ImportRadicadoJob($batchId, $processNumber, $organizationId))
                ->onQueue($queue)
                ->delay(now()->addSeconds($accumulatedDelay));
        }

        return $jobs;
    }

    /**
     * Resolves a random delay between jobs (1 to 4 seconds) for testing purposes.
     *
     * @return int Delay in seconds
     *
     * @throws RandomException
     */
    private function resolveRandomDelaySeconds(): int
    {
        return random_int(1, 4);
    }

    /**
     * Updates the batch to COMPLETED status and logs the summary.
     *
     * @param  ProcessImportBatch  $importBatch  Batch model to update
     */
    private function markBatchCompleted(ProcessImportBatch $importBatch): void
    {
        $importBatch->update([
            'status' => ProcessImportBatch::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->log('Import batch completed', [
            'batch_id' => $importBatch->id,
            'excel_total_count' => $importBatch->excel_total_count,
            'total_count' => $importBatch->total_count,
            'success_count' => $importBatch->success_count,
            'failed_count' => $importBatch->failed_count,
            'multiple_instances_count' => $importBatch->multiple_instances_count,
            'errors_sample' => array_slice($importBatch->errors ?? [], 0, 5),
        ]);
    }

    /**
     * Builds the ProcessImportReport DTO from the completed batch.
     *
     * @param  ProcessImportBatch  $importBatch  Completed batch model
     */
    private function buildReport(ProcessImportBatch $importBatch): ProcessImportReport
    {
        return new ProcessImportReport(
            batchId: $importBatch->id,
            organizationName: $importBatch->organization->name ?? '',
            excelTotalCount: $importBatch->excel_total_count,
            totalCount: $importBatch->total_count,
            multipleInstancesCount: $importBatch->multiple_instances_count,
            successCount: $importBatch->success_count,
            failedCount: $importBatch->failed_count,
            errors: $importBatch->errors ?? [],
            completedAt: $importBatch->completed_at,
        );
    }

    /**
     * Builds the HTTP response body, including skipped count only when greater than zero.
     *
     * @param  ProcessImportBatch  $batch  Dispatched batch model
     * @param  int  $skipped  Number of already registered radicados skipped
     * @return array{message: string, batch_id: string, skipped_already_registered?: int}
     */
    private function buildResponse(ProcessImportBatch $batch, int $skipped): array
    {
        $body = [
            'message' => __('process.import_started'),
            'batch_id' => $batch->id,
        ];

        if ($skipped > 0) {
            $body['skipped_already_registered'] = $skipped;
        }

        return $body;
    }

    /**
     * Writes an info log entry to the configured import channel.
     *
     * @param  string  $message  Log message
     * @param  array<string, mixed>  $context  Additional context data
     */
    private function log(string $message, array $context = []): void
    {
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->info($message, $context);
    }
}

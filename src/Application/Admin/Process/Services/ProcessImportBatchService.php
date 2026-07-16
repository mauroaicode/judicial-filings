<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Random\RandomException;
use Src\Application\Admin\Process\DTOs\ProcessImportDataResult;
use Src\Application\Shared\DTOs\ProcessImportReport;
use Src\Application\Shared\Jobs\FinalizeProcessImportBatchJob;
use Src\Application\Shared\Jobs\ImportRadicadoJob;
use Src\Application\Shared\Jobs\ImportRadicadoSamaiJob;
use Src\Application\Shared\Services\Notification\ImportReportNotificationService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Domain\Process\Models\ProcessImportBatch;
use Throwable;

readonly class ProcessImportBatchService
{
    public function __construct(
        private ImportReportNotificationService $notificationService,
        private ProcessSyncService $processSyncService,
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
        $jobs = $this->buildJobs($data->toEnqueue, $data->organizationId, $batch->id, $data->source);
        $queueName = config('process-import.jobs.import_radicado.queue');

        $laravelBatch = Bus::batch($jobs)
            ->allowFailures()
            ->onQueue($queueName)
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
     * Marks batch as completed, sends import report notifications and builds the
     * registration consolidado for recent actuaciones (single digest for the whole batch).
     */
    public function finalize(string $importBatchId): void
    {
        $importBatch = ProcessImportBatch::query()
            ->with('organization', 'requestedByUser')
            ->find($importBatchId);

        if (! $importBatch instanceof ProcessImportBatch) {
            return;
        }

        if ($importBatch->status === ProcessImportBatch::STATUS_COMPLETED) {
            try {
                $this->dispatchImportRegistrationDigest($importBatch);
            } catch (\Throwable $e) {
                Log::channel(config('process-import.log_channel', 'process_import'))
                    ->error('Import batch registration digest failed', [
                        'batch_id' => $importBatch->id,
                        'error' => $e->getMessage(),
                    ]);
            }

            return;
        }

        $this->markBatchCompleted($importBatch);

        $report = $this->buildReport($importBatch);

        try {
            $this->notificationService->notifyAdmin($report);
            $this->notificationService->notifyOrganization($report, $importBatch->organization_id);
        } catch (\Throwable $e) {
            Log::channel(config('process-import.log_channel', 'process_import'))
                ->error('Import batch notification dispatch failed', [
                    'batch_id' => $importBatch->id,
                    'error' => $e->getMessage(),
                ]);
        }

        try {
            $this->dispatchImportRegistrationDigest($importBatch);
        } catch (\Throwable $e) {
            Log::channel(config('process-import.log_channel', 'process_import'))
                ->error('Import batch registration digest failed', [
                    'batch_id' => $importBatch->id,
                    'error' => $e->getMessage(),
                ]);
        }
    }

    /**
     * Builds the registration consolidado only for radicados imported in this batch.
     */
    private function dispatchImportRegistrationDigest(ProcessImportBatch $importBatch): void
    {
        /** @var array<int, string> $processNumbers */
        $processNumbers = $importBatch->enqueued_process_numbers ?? [];

        $this->processSyncService->dispatchRegistrationDigestIfPending(
            $importBatch->organization_id,
            $processNumbers !== [] ? $processNumbers : null,
        );
    }

    /**
     * Dispatches finalize when every radicado job has reported success or failure.
     */
    public function tryDispatchFinalize(string $importBatchId): void
    {
        $batch = ProcessImportBatch::query()->find($importBatchId);

        if (! $batch instanceof ProcessImportBatch) {
            return;
        }

        if ($batch->status !== ProcessImportBatch::STATUS_PROCESSING) {
            return;
        }

        if (($batch->success_count + $batch->failed_count) < $batch->total_count) {
            return;
        }

        dispatch(new FinalizeProcessImportBatchJob($importBatchId));
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
     * Builds the job array with staggered delays per index.
     * Uses ImportRadicadoSamaiJob when source is 'samai', ImportRadicadoJob otherwise.
     *
     * @param  array<int, string>  $toEnqueue  Process numbers to enqueue
     * @param  string  $organizationId  Organization identifier
     * @param  string  $batchId  Import batch DB identifier
     * @param  string  $source  Process data source slug
     * @return array<int, ImportRadicadoJob|ImportRadicadoSamaiJob>
     */
    private function buildJobs(array $toEnqueue, string $organizationId, string $batchId, string $source = 'judicial_branch'): array
    {
        $queue = config('process-import.jobs.import_radicado.queue');
        $jobs = [];
        $accumulatedDelay = 0;
        $isSamai = $source === 'samai';

        foreach ($toEnqueue as $processNumber) {
            $accumulatedDelay += $this->resolveRandomDelaySeconds();

            $job = $isSamai
                ? new ImportRadicadoSamaiJob($batchId, $processNumber, $organizationId)
                : new ImportRadicadoJob($batchId, $processNumber, $organizationId);

            $jobs[] = $job->onQueue($queue)->delay(now()->addSeconds($accumulatedDelay));
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

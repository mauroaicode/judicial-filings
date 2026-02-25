<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Random\RandomException;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Src\Application\Shared\Exceptions\ApiProxyFailureException;
use Src\Domain\Process\Models\ProcessImportBatch;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ImportRadicadoJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 120;

    public function __construct(
        public string $processImportBatchId,
        public string $processNumber,
        public string $organizationId,
    ) {
        $config = config('process-import.jobs.import_radicado', []);
        $this->tries = $config['tries'] ?? 4;
        $this->timeout = (int) ($config['timeout'] ?? 120);
    }

    /**
     * Registers the radicado against Rama Judicial and updates the batch counters.
     */
    public function handle(RegisterProcessService $registerProcessService): void
    {
        if ($this->isBatchCancelled()) {
            return;
        }

        $this->log('info', 'Import radicado job started', ['attempt' => $this->attempts()]);

        try {
            $result = $registerProcessService->handle($this->processNumber, $this->organizationId);

            $this->incrementBatchSuccess($result->registeredCount);

            if ($result->hasMultipleInstances) {
                $this->incrementMultipleInstancesCount();
            }

            $this->log('info', 'Import radicado finished successfully', [
                'registered_count' => $result->registeredCount,
                'has_multiple_instances' => $result->hasMultipleInstances,
            ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Records the failure in the batch when Laravel moves the job to failed_jobs.
     *
     * @throws Throwable
     */
    public function failed(?Throwable $e = null): void
    {
        $reason = $e instanceof Throwable
            ? $e->getMessage()
            : __('process.import_job_max_attempts_exceeded');

        $this->appendBatchError($reason);
    }

    /**
     * Returns true and logs when the parent Laravel batch has been cancelled.
     */
    private function isBatchCancelled(): bool
    {
        if (! $this->batch()?->cancelled()) {
            return false;
        }

        $this->log('info', 'Import radicado skipped: batch cancelled');

        return true;
    }

    /**
     * Routes the exception: retryable (with release), or definitive failure.
     *
     * Empty-processes (ApiEmptyProcessesException) gets a limited number of retries because
     * Rama Judicial occasionally returns HTTP 200 with an empty array under load. After all
     * retries are exhausted it is treated as a genuine "radicado does not exist" failure.
     */
    private function handleException(Throwable $e): void
    {
        [$releaseSeconds, $maxAttempts] = $this->resolveRetryConfig($e);

        if ($this->attempts() <= $maxAttempts) {
            $this->log('warning', 'Import radicado failed, will retry', [
                'reason' => $e->getMessage(),
                'exception' => $e::class,
                'attempt' => $this->attempts(),
                'max_attempts' => $maxAttempts,
                'release_seconds' => $releaseSeconds,
            ]);
            $this->release($releaseSeconds);

            return;
        }

        $this->log('error', 'Import radicado failed (final)', [
            'reason' => $e->getMessage(),
            'exception' => $e::class,
            'attempt' => $this->attempts(),
        ]);
        $this->appendBatchError($e->getMessage());
    }

    /**
     * Returns [releaseSeconds, maxAttempts] based on the exception type.
     *
     * - Proxy failure (cURL 7/28): retry immediately (5 s) so array_rand picks
     *   a different IP. High max_attempts because proxy failures are transient.
     * - Empty processes (200 + vacío): Rama Judicial returns empty transiently under load.
     *   Small number of retries; if still empty after all retries → definitive failure.
     * - 403/429: jitter applied to spread simultaneous retries.
     * - Not found: longer wait; may be transient (timeout, API failure).
     * - Generic: shorter wait with fewer retries.
     *
     * @return array{int, int}
     *
     * @throws RandomException
     */
    private function resolveRetryConfig(Throwable $e): array
    {
        if ($e instanceof ApiProxyFailureException) {
            return [
                (int) config('process-import.retry_release_seconds_for_proxy_failure', 5),
                (int) config('process-import.retry_max_attempts_for_proxy_failure', 10),
            ];
        }

        if ($e instanceof ApiEmptyProcessesException) {
            return [
                (int) config('process-import.retry_release_seconds_for_empty', 120),
                (int) config('process-import.retry_max_attempts_for_empty', 3),
            ];
        }

        if ($e instanceof ApiForbiddenOrRateLimitException) {
            $base = (int) config('process-import.retry_release_seconds_for_rate_limit', 180);
            $jitter = (int) ceil($base * 0.20);

            return [random_int($base, $base + $jitter), (int) config('process-import.retry_max_attempts_for_not_found', 10)];
        }

        if ($this->isNotFoundError($e)) {
            return [
                (int) config('process-import.retry_release_seconds_for_not_found', 300),
                (int) config('process-import.retry_max_attempts_for_not_found', 10),
            ];
        }

        return [
            (int) config('process-import.retry_release_seconds', 120),
            (int) config('process-import.retry_max_attempts', 2),
        ];
    }

    /**
     * "Does not exist in Rama Judicial" may be transient: rate limit, timeout or API failure.
     */
    private function isNotFoundError(Throwable $e): bool
    {
        $message = $e->getMessage();

        if (str_contains($message, 'no existe') && str_contains($message, 'Rama Judicial')) {
            return true;
        }

        if (str_contains($message, 'does not exist') && str_contains($message, 'Judicial Branch')) {
            return true;
        }

        return $e instanceof NotFoundHttpException;
    }

    /**
     * Atomically increments success_count on the batch record.
     *
     * @param  int  $count  Number of registered instances (may be > 1 for double-instance radicados)
     */
    private function incrementBatchSuccess(int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        DB::transaction(function () use ($count): void {
            $batch = $this->findBatchForUpdate();
            $batch?->increment('success_count', $count);
        });
    }

    /**
     * Atomically increments multiple_instances_count when the radicado has more than one judicial instance.
     */
    private function incrementMultipleInstancesCount(): void
    {
        DB::transaction(function (): void {
            $batch = $this->findBatchForUpdate();
            $batch?->increment('multiple_instances_count');
        });
    }

    /**
     * Atomically appends an error entry and increments failed_count on the batch record.
     *
     * @throws Throwable
     */
    private function appendBatchError(string $reason): void
    {
        DB::transaction(function () use ($reason): void {
            $batch = $this->findBatchForUpdate();

            if (! $batch instanceof \Src\Domain\Process\Models\ProcessImportBatch) {
                return;
            }

            /** @var array<int, array{process_number: string, reason: string}> $errors */
            $errors = $batch->errors;
            $errors[] = ['process_number' => $this->processNumber, 'reason' => $reason];

            $batch->update([
                'failed_count' => $batch->failed_count + 1,
                'errors' => $errors,
            ]);
        });
    }

    /**
     * Fetches the batch row with a write lock for safe counter-updates.
     */
    private function findBatchForUpdate(): ?ProcessImportBatch
    {
        return ProcessImportBatch::query()
            ->where('id', $this->processImportBatchId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Writes a log entry to the import channel, always including process_number and batch_id.
     *
     * @param  string  $level  PSR log level (info|warning|error)
     * @param  string  $message  Log message
     * @param  array<string, mixed>  $context  Additional context data
     */
    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->$level($message, array_merge([
                'process_number' => $this->processNumber,
                'batch_id' => $this->processImportBatchId,
            ], $context));
    }
}

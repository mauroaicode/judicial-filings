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
     * Registers the radicado against the Portal Judicial and updates the batch counters.
     */
    public function handle(RegisterProcessService $registerProcessService): void
    {
        if ($this->isBatchCancelled()) {
            return;
        }

        $this->log('info', 'Import radicado job started', ['attempt' => $this->attempts()]);

        try {
            $seed = $this->processNumber.':'.$this->attempts();
            // requested_by es el User administrativo (panel), no app_users.id — no pasarlo como appUserId
            // (evita FK en ai_chats). Los chats se pueden crear después desde la app de abogados.
            $result = $registerProcessService->handle(
                $this->processNumber,
                $this->organizationId,
                null,
                $seed,
            );

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
     * Portal Judicial occasionally returns HTTP 200 with an empty array under load. After all
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
     * - Proxy failure (cURL 7/28/56): retry quickly so the pool selects a
     *   different IP. High max_attempts because proxy failures are transient.
     * - Empty processes (200 + vacío): Portal Judicial returns empty transiently
     *   under load. Small retries; if still empty → definitive failure.
     * - 403/429 with Retry-After header: honour the server-mandated wait exactly.
     * - 403/429 with proxy pool: exponential backoff per attempt (fresh IP each time).
     * - 403/429 without proxy: exponential backoff with longer base.
     * - Not found: longer wait; may be transient (timeout, API failure).
     * - Generic: shorter wait with fewer retries.
     *
     * Exponential backoff formula: (2 ** attempt) + random_int(1, 3) seconds.
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
            $maxAttempts = (int) config('process-import.retry_max_attempts_for_rate_limit', 10);

            // Honour Retry-After header when the server provides it explicitly
            if ($e->retryAfter !== null && $e->retryAfter > 0) {
                $this->log('warning', 'Retry-After header received — honouring server wait', [
                    'retry_after_seconds' => $e->retryAfter,
                    'attempt' => $this->attempts(),
                ]);

                return [$e->retryAfter, $maxAttempts];
            }

            // With rotating proxy: exponential backoff per attempt.
            // Each retry gets a fresh IP, so the base delay is short.
            if (config('judicial-branch.proxy.enabled', false)) {
                $base = (int) config('process-import.retry_release_seconds_for_rate_limit_proxy', 5);
                $delay = $this->exponentialBackoff($this->attempts(), $base);

                return [$delay, $maxAttempts];
            }

            // Without proxy: exponential backoff with a longer base to avoid
            // hammering the API from the same IP.
            $base = (int) config('process-import.retry_release_seconds_for_rate_limit', 60);
            $delay = $this->exponentialBackoff($this->attempts(), $base);

            return [$delay, (int) config('process-import.retry_max_attempts_for_rate_limit', 5)];
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
     * Computes an exponential backoff delay with jitter.
     *
     * Formula: max($base, (2 ** $attempt)) + random_int(1, 3) seconds.
     * Capped at 3600 seconds (1 hour) to prevent unbounded waits.
     *
     * @throws RandomException
     */
    private function exponentialBackoff(int $attempt, int $base = 5): int
    {
        $exponential = (int) min(3600, max($base, 2 ** $attempt));

        return $exponential + random_int(1, 3);
    }

    /**
     * "Does not exist in the Portal Judicial" may be transient: rate limit, timeout or API failure.
     */
    private function isNotFoundError(Throwable $e): bool
    {
        $message = $e->getMessage();

        if (str_contains($message, 'no existe') && str_contains($message, 'Portal Judicial')) {
            return true;
        }

        if (str_contains($message, 'does not exist') && str_contains($message, 'Portal Judicial')) {
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

            if (! $batch instanceof ProcessImportBatch) {
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

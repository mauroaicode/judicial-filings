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
use Illuminate\Support\Facades\RateLimiter;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Src\Domain\Process\Models\ProcessImportBatch;
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

    public function handle(RegisterProcessService $registerProcessService): void
    {
        $logChannel = config('process-import.log_channel', 'process_import');

        if ($this->batch()?->cancelled()) {
            Log::channel($logChannel)->info('Import radicado skipped: batch cancelled', [
                'process_number' => $this->processNumber,
                'batch_id' => $this->processImportBatchId,
            ]);

            return;
        }

        Log::channel($logChannel)->info('Import radicado job started', [
            'process_number' => $this->processNumber,
            'batch_id' => $this->processImportBatchId,
            'attempt' => $this->attempts(),
        ]);

        $rateLimitKey = 'judicial-api-import';
        $maxPerMinute = (int) config('process-import.rate_limit_per_minute', 6);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxPerMinute)) {
            Log::channel($logChannel)->warning('Import radicado rate limit hit, releasing job for 60s', [
                'process_number' => $this->processNumber,
                'batch_id' => $this->processImportBatchId,
                'max_per_minute' => $maxPerMinute,
            ]);
            $this->release(60);

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $registerResult = $registerProcessService->handle($this->processNumber, $this->organizationId);

            Log::channel($logChannel)->info('Import radicado finished successfully', [
                'process_number' => $this->processNumber,
                'batch_id' => $this->processImportBatchId,
                'registered_count' => $registerResult->registeredCount,
            ]);

            $this->incrementBatchSuccess($registerResult->registeredCount);
        } catch (Throwable $e) {
            // 200 con procesos vacíos: fallo definitivo, no gastar reintentos. Se muestra en el reporte final.
            if ($e instanceof ApiEmptyProcessesException) {
                Log::channel($logChannel)->info('Import radicado failed (empty procesos, no retry)', [
                    'process_number' => $this->processNumber,
                    'batch_id' => $this->processImportBatchId,
                    'reason' => $e->getMessage(),
                ]);
                $this->appendBatchError($e->getMessage());

                return;
            }

            $isTransientRetry = $this->isNotFoundError($e) || $e instanceof ApiForbiddenOrRateLimitException;
            $retryMaxAttempts = $isTransientRetry
                ? (int) config('process-import.retry_max_attempts_for_not_found', 10)
                : (int) config('process-import.retry_max_attempts', 2);
            $retryReleaseSeconds = $isTransientRetry
                ? (int) config('process-import.retry_release_seconds_for_not_found', 300)
                : (int) config('process-import.retry_release_seconds', 120);

            if ($this->attempts() <= $retryMaxAttempts) {
                Log::channel($logChannel)->warning('Import radicado failed, will retry later', [
                    'process_number' => $this->processNumber,
                    'batch_id' => $this->processImportBatchId,
                    'reason' => $e->getMessage(),
                    'exception' => $e::class,
                    'attempt' => $this->attempts(),
                    'max_attempts' => $retryMaxAttempts,
                    'release_seconds' => $retryReleaseSeconds,
                    'treated_as_transient' => $isTransientRetry,
                ]);
                $this->release($retryReleaseSeconds);

                return;
            }

            Log::channel($logChannel)->error('Import radicado failed (final)', [
                'process_number' => $this->processNumber,
                'batch_id' => $this->processImportBatchId,
                'reason' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->appendBatchError($e->getMessage());
        }
    }

    /**
     * "No existe en la Rama Judicial" puede ser transitorio (rate limit, timeout, fallo API).
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
        $notFoundClass = 'Symfony\Component\HttpKernel\Exception\NotFoundHttpException';
        if ($e instanceof $notFoundClass) {
            return true;
        }

        return false;
    }

    /**
     * Llamado por Laravel cuando el job va a failed_jobs (MaxAttemptsExceeded u otra excepción no capturada).
     * Así registramos en el batch los radicados que no se procesaron por agotar intentos (ej. rate limit).
     */
    public function failed(?Throwable $e = null): void
    {
        $reason = $e instanceof Throwable
            ? $e->getMessage()
            : __('process.import_job_max_attempts_exceeded');
        $this->appendBatchError($reason);
    }

    private function incrementBatchSuccess(int $count = 1): void
    {
        if ($count < 1) {
            return;
        }
        DB::transaction(function () use ($count): void {
            $batch = ProcessImportBatch::query()
                ->where('id', $this->processImportBatchId)
                ->lockForUpdate()
                ->first();

            if ($batch) {
                $batch->increment('success_count', $count);
            }
        });
    }

    private function appendBatchError(string $reason): void
    {
        DB::transaction(function () use ($reason): void {
            $batch = ProcessImportBatch::query()
                ->where('id', $this->processImportBatchId)
                ->lockForUpdate()
                ->first();

            if ($batch) {
                /** @var array<int, array{process_number: string, reason: string}> $errors */
                $errors = $batch->errors;
                $errors[] = ['process_number' => $this->processNumber, 'reason' => $reason];
                $batch->update([
                    'failed_count' => $batch->failed_count + 1,
                    'errors' => $errors,
                ]);
            }
        });
    }
}

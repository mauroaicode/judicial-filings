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
use Src\Application\Admin\Process\Services\ProcessImportBatchService;
use Src\Application\AppUser\Process\Services\RegisterSamaiProcessService;
use Src\Application\Shared\Exceptions\SamaiDiscoveryTimeoutException;
use Src\Domain\Process\Models\ProcessImportBatch;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Job de cola para importación masiva de radicados desde SAMAI.
 *
 * Espejo de ImportRadicadoJob pero delega en RegisterSamaiProcessService
 * en lugar de RegisterProcessService (Rama Judicial).
 */
class ImportRadicadoSamaiJob implements ShouldQueue
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

    /**
     * Espera entre reintentos: 60s → 180s.
     * Permite que servidores lentos de SAMAI respondan en el segundo intento.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 180];
    }

    public function __construct(
        public string $processImportBatchId,
        public string $processNumber,
        public string $organizationId,
    ) {
        $config = config('process-import.jobs.import_radicado', []);
        $this->tries = $config['tries'] ?? 3;
        $this->timeout = (int) ($config['timeout'] ?? 120);
    }

    /**
     * Registra el radicado desde SAMAI y actualiza los contadores del batch.
     */
    public function handle(RegisterSamaiProcessService $registerSamaiProcessService): void
    {
        if ($this->isBatchCancelled()) {
            return;
        }

        $this->log('info', 'ImportRadicadoSamaiJob started', ['attempt' => $this->attempts()]);

        try {
            $seed = $this->processNumber.':'.$this->attempts();

            $result = $registerSamaiProcessService->handle(
                $this->processNumber,
                $this->organizationId,
                null,
                $seed,
                deferRegistrationDigest: true,
            );

            $this->incrementBatchSuccess($result->registeredCount);

            if ($result->hasMultipleInstances) {
                $this->incrementMultipleInstancesCount();
            }

            $this->tryDispatchFinalizeImportBatch();

            $this->log('info', 'ImportRadicadoSamaiJob finished', [
                'registered_count' => $result->registeredCount,
                'has_multiple_instances' => $result->hasMultipleInstances,
            ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function failed(?Throwable $e = null): void
    {
        $reason = $e instanceof Throwable
            ? $e->getMessage()
            : __('process.import_job_max_attempts_exceeded');

        $this->appendBatchError($reason);
    }

    private function isBatchCancelled(): bool
    {
        if (! $this->batch()?->cancelled()) {
            return false;
        }

        $this->log('info', 'ImportRadicadoSamaiJob skipped: batch cancelled');

        return true;
    }

    private function handleException(Throwable $e): void
    {
        if ($e instanceof SamaiDiscoveryTimeoutException) {
            $maxAttempts = (int) config('process-import.retry_max_attempts_for_samai_discovery_timeout', 5);
            $releaseSeconds = (int) config('process-import.retry_release_seconds_for_samai_discovery_timeout', 180);
        } elseif ($e instanceof NotFoundHttpException) {
            $maxAttempts = (int) config('process-import.retry_max_attempts_for_not_found', 3);
            $releaseSeconds = (int) config('process-import.retry_release_seconds_for_not_found', 120);
        } else {
            $maxAttempts = (int) config('process-import.retry_max_attempts', 2);
            $releaseSeconds = (int) config('process-import.retry_release_seconds', 60);
        }

        if ($this->attempts() <= $maxAttempts) {
            $this->log('warning', 'ImportRadicadoSamaiJob failed, will retry', [
                'reason' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'release_seconds' => $releaseSeconds,
                'exception' => $e::class,
            ]);
            $this->release($releaseSeconds);

            return;
        }

        $this->log('error', 'ImportRadicadoSamaiJob failed (final)', [
            'reason' => $e->getMessage(),
            'attempt' => $this->attempts(),
            'exception' => $e::class,
        ]);
        $this->appendBatchError($e->getMessage());
    }

    private function incrementBatchSuccess(int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        DB::transaction(function () use ($count): void {
            $this->findBatchForUpdate()?->increment('success_count', $count);
        });
    }

    private function incrementMultipleInstancesCount(): void
    {
        DB::transaction(function (): void {
            $this->findBatchForUpdate()?->increment('multiple_instances_count');
        });
    }

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

            $batch->update(['failed_count' => $batch->failed_count + 1, 'errors' => $errors]);
        });

        $this->tryDispatchFinalizeImportBatch();
    }

    private function findBatchForUpdate(): ?ProcessImportBatch
    {
        return ProcessImportBatch::query()
            ->where('id', $this->processImportBatchId)
            ->lockForUpdate()
            ->first();
    }

    private function tryDispatchFinalizeImportBatch(): void
    {
        resolve(ProcessImportBatchService::class)->tryDispatchFinalize($this->processImportBatchId);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->$level($message, array_merge([
                'process_number' => $this->processNumber,
                'batch_id' => $this->processImportBatchId,
                'source' => 'samai',
            ], $context));
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Application\AppUser\Process\Services\RegisterSamaiProcessService;
use Src\Application\Shared\Exceptions\SamaiDiscoveryTimeoutException;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Notifications\ProcessDataImportedNotification;
use Src\Domain\Notification\Notifications\ProcessImportFailedNotification;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessRegistrationLog;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Job de cola para el registro de procesos SAMAI cuando el historial de actuaciones
 * supera el umbral de registro inline (SAMAI_REGISTRATION_INLINE_MAX_ACTUACIONES).
 *
 * Espejo de SyncJudicialBranchJob pero usando RegisterSamaiProcessService.
 * Comparte la cola process-import con el alta admin para no competir con judicial-sync.
 */
class SyncSamaiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    /** Retries cover SAMAI discovery timeouts; same door as admin ImportRadicadoSamaiJob. */
    public int $tries = 5;

    /**
     * Espera entre reintentos: 5s → 15s → 30s → 60s (deadlock) / longer gaps for API timeouts.
     *
     * @var list<int>
     */
    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        public string $processNumber,
        public string $organizationId,
        public AppUser $appUser,
        public ?ProcessLawyerRole $lawyerRole = null,
    ) {
        $config = config('process-import.jobs.import_radicado', []);
        $this->tries = (int) ($config['tries'] ?? 30);
        $this->timeout = (int) ($config['timeout'] ?? 600);
    }

    /**
     * @throws Throwable
     */
    public function handle(RegisterSamaiProcessService $registerSamaiProcessService): void
    {
        try {
            $seed = $this->processNumber.':'.$this->attempts();

            $result = $registerSamaiProcessService->handle(
                $this->processNumber,
                $this->organizationId,
                $this->lawyerRole,
                $seed,
                $this->appUser->id,
            );

            $process = $result->getFirstProcess();

            if ($process instanceof Process) {
                $this->updateLogStatus('success');
                $this->appUser->notify(new ProcessDataImportedNotification($process));
            } else {
                $this->updateLogStatus('failed', 'No process was imported from SAMAI.');
                $this->appUser->notify(new ProcessImportFailedNotification(
                    $this->processNumber,
                    'No process was imported from SAMAI.'
                ));
            }
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function failed(?Throwable $e = null): void
    {
        $message = $e instanceof Throwable
            ? $e->getMessage()
            : __('process.import_job_max_attempts_exceeded');

        $this->updateLogStatus('failed', $message);
        $this->appUser->notify(new ProcessImportFailedNotification($this->processNumber, $message));
    }

    /**
     * @throws Throwable
     */
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
            $this->release($releaseSeconds);

            return;
        }

        throw $e;
    }

    private function updateLogStatus(string $status, ?string $error = null): void
    {
        ProcessRegistrationLog::query()
            ->where('process_number', $this->processNumber)
            ->where('organization_id', $this->organizationId)
            ->where('app_user_id', $this->appUser->id)
            ->where('status', 'pending')
            ->latest()
            ->first()
            ?->update(['status' => $status, 'error' => $error]);
    }
}

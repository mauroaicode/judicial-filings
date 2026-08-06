<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Application\AppUser\Process\Services\RegisterSamaiProcessService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Notifications\ProcessDataImportedNotification;
use Src\Domain\Notification\Notifications\ProcessImportFailedNotification;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessRegistrationLog;
use Throwable;

/**
 * Job de cola para el registro de procesos SAMAI cuando el historial de actuaciones
 * supera el umbral de registro inline (SAMAI_REGISTRATION_INLINE_MAX_ACTUACIONES).
 *
 * Espejo de SyncJudicialBranchJob pero usando RegisterSamaiProcessService.
 */
class SyncSamaiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    /** Retries cover MySQL deadlocks when daily sync is also writing processes. */
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
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(RegisterSamaiProcessService $registerSamaiProcessService): void
    {
        try {
            $result = $registerSamaiProcessService->handle(
                $this->processNumber,
                $this->organizationId,
                $this->lawyerRole,
                $this->processNumber,
                $this->appUser->id,
            );

            $process = $result->getFirstProcess();

            if ($process instanceof Process) {
                $this->updateLogStatus('success');
                $this->appUser->notify(new ProcessDataImportedNotification($process));
            } else {
                $this->updateLogStatus('failed', 'No process was imported from SAMAI.');
            }
        } catch (Throwable $e) {
            $this->updateLogStatus('failed', $e->getMessage());
            $this->appUser->notify(new ProcessImportFailedNotification($this->processNumber, $e->getMessage()));

            throw $e;
        }
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

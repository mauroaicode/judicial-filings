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

    /**
     * Espera entre reintentos: 60s → 120s → 300s.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 120, 300];
    }

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

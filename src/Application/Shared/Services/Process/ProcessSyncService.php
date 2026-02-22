<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Traits\MapsJudicialActuacionTrait;
use Src\Application\Shared\Traits\MapsJudicialSujetoTrait;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessSubject;

class ProcessSyncService
{
    use MapsJudicialActuacionTrait;
    use MapsJudicialSujetoTrait;

    public function __construct(
        private readonly JudicialBranchConsultService $judicialService,
        /** @phpstan-ignore property.onlyWritten (used when processActionAlertNotificationService->handle is uncommented in syncActuaciones) */
        private readonly ProcessActionAlertNotificationService $processActionAlertNotificationService
    ) {}

    public function handle(Process $process): void
    {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        $apiProcessId = $process->process_id;

        $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId);

        if (! $actionsResult->isSuccessful) {
            Log::channel($logChannel)->error('ProcessSyncService: failed to fetch actuaciones', [
                'process_id' => $process->id,
            ]);
            throw new \RuntimeException(__('process.sync_failed_actuaciones'));
        }

        $subjectsResult = $this->judicialService->fetchSubjectsByProcess($apiProcessId);
        if (! $subjectsResult->isSuccessful) {
            Log::channel($logChannel)->error('ProcessSyncService: failed to fetch sujetos', [
                'process_id' => $process->id,
            ]);
            throw new \RuntimeException(__('process.sync_failed_sujetos'));
        }

        $this->syncActuaciones($process, $actionsResult->data);
        $this->syncSujetos($process, $subjectsResult->data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiActuaciones
     */
    private function syncActuaciones(Process $process, array $apiActuaciones): void
    {

        foreach ($apiActuaciones as $apiActuacion) {
            $idReg = (int) ($apiActuacion['idRegActuacion'] ?? 0);
            if ($idReg === 0) {
                continue;
            }

            if (ProcessAction::query()->existsByActionRegistrationId($idReg)) {
                continue;
            }

            $attributes = $this->mapApiActuacionToAttributes($apiActuacion);
            $attributes['process_id'] = $process->id;

            $action = ProcessAction::query()->create($attributes);

            //                        $this->processActionAlertNotificationService->handle($action, $process);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiSujetos
     */
    private function syncSujetos(Process $process, array $apiSujetos): void
    {
        foreach ($apiSujetos as $apiSujeto) {
            $idReg = (int) ($apiSujeto['idRegSujeto'] ?? 0);
            if ($idReg === 0) {
                continue;
            }

            $attributes = $this->mapApiSujetoToAttributes($apiSujeto);
            $subject = ProcessSubject::query()->firstOrCreate(
                ['subject_registration_id' => $idReg],
                $attributes
            );

            $process->subjects()->syncWithoutDetaching([$subject->id]);
        }
    }

    /**
     * Sync actuaciones and sujetos for all process instances of a radicado with one API call.
     * Only processes that are active in at least one organization are synced.
     */
    public function syncByProcessNumber(string $processNumber): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        $processes = Process::query()
            ->where('process_number', $processNumber)
            ->whereHas('organizations', fn (Builder $q) => $q->where('organization_processes.is_active', true))
            ->get();

        if ($processes->isEmpty()) {
            Log::channel($channel)->info('ProcessSyncService: no active processes for radicado', [
                'process_number' => $processNumber,
            ]);

            return;
        }

        $representative = $processes->first();
        $apiProcessId = (int) $representative->process_id;

        $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId);

        if (! $actionsResult->isSuccessful) {
            Log::channel($channel)->error('ProcessSyncService: failed to fetch actuaciones', [
                'process_number' => $processNumber,
            ]);

            return;
        }

        $subjectsResult = $this->judicialService->fetchSubjectsByProcess($apiProcessId);
        if (! $subjectsResult->isSuccessful) {
            Log::channel($channel)->error('ProcessSyncService: failed to fetch sujetos', [
                'process_number' => $processNumber,
            ]);

            return;
        }

        $apiActuaciones = is_array($actionsResult->data) ? $actionsResult->data : [];
        $apiSujetos = is_array($subjectsResult->data) ? $subjectsResult->data : [];

        $this->syncActuaciones($representative, $apiActuaciones);
        $this->syncSujetos($representative, $apiSujetos);
    }
}

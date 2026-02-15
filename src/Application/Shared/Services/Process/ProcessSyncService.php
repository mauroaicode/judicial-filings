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
        private readonly ProcessActionAlertNotificationService $processActionAlertNotificationService
    ) {}

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

        // Sync only to the representative process so we do not duplicate action_registration_id (global unique).
        $this->syncActuaciones($representative, $apiActuaciones);
        $this->syncSujetos($representative, $apiSujetos);
    }

    public function handle(Process $process): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        $apiProcessId = (int) $process->process_id;

        $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId);

        if (! $actionsResult->isSuccessful) {
            Log::channel($channel)->error('ProcessSyncService: failed to fetch actuaciones', [
                'process_id' => $process->id,
            ]);

            return;
        }

        $subjectsResult = $this->judicialService->fetchSubjectsByProcess($apiProcessId);
        if (! $subjectsResult->isSuccessful) {
            Log::channel($channel)->error('ProcessSyncService: failed to fetch sujetos', [
                'process_id' => $process->id,
            ]);

            return;
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

            // Si la actuación ya existe (globalmente), no hacer nada y continuar con la siguiente.
            if (ProcessAction::query()->where('action_registration_id', $idReg)->exists()) {
                continue;
            }

            $attributes = $this->mapApiActuacionToAttributes($apiActuacion);
            $attributes['process_id'] = $process->id;

            $action = ProcessAction::query()->create($attributes);

            $this->processActionAlertNotificationService->handle($action, $process);
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

            // Si el sujeto ya existe (globalmente), no hacer nada y continuar con la siguiente.
            if (ProcessSubject::query()->where('subject_registration_id', $idReg)->exists()) {
                continue;
            }

            $attributes = $this->mapApiSujetoToAttributes($apiSujeto);
            $attributes['process_id'] = $process->id;

            ProcessSubject::query()->create($attributes);
        }
    }
}

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

    public function handle(Process $process, bool $notify = true): void
    {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        $apiProcessId = $process->process_id;

        $this->judicialService->withSeed($process->process_number);

        $onlyFirstPage = $process->actions()->exists();
        $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId, $onlyFirstPage);

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

        $this->syncActuaciones($process, $actionsResult->data, $notify);
        $this->syncSujetos($process, $subjectsResult->data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiActuaciones
     */
    private function syncActuaciones(Process $process, array $apiActuaciones, bool $notify = true): void
    {

        foreach ($apiActuaciones as $apiActuacion) {
            $idReg = (int) ($apiActuacion['idRegActuacion'] ?? 0);
            if ($idReg === 0) {
                continue;
            }

            if (ProcessAction::query()->existsByActionRegistrationId($process->id, $idReg)) {
                continue;
            }

            $attributes = $this->mapApiActuacionToAttributes($apiActuacion);
            $attributes['process_id'] = $process->id;

            $action = ProcessAction::query()->create($attributes);

            if ($notify) {
                $this->processActionAlertNotificationService->handle($action, $process);
            }
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
    public function syncByProcessNumber(string $processNumber, bool $notify = true): void
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

        $this->judicialService->withSeed($processNumber);

        foreach ($processes as $process) {
            $apiProcessId = (int) $process->process_id;

            // Optimización: si ya tenemos actuaciones, solo consultamos la página 1 para detectar novedades.
            // Si es un proceso nuevo (instancia recién detectada), traemos todo el historial.
            $onlyFirstPage = $process->actions()->exists();

            $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId, $onlyFirstPage);
            if (! $actionsResult->isSuccessful) {
                Log::channel($channel)->error('ProcessSyncService: failed to fetch actuaciones', [
                    'process_number' => $processNumber,
                    'process_id' => $process->id,
                ]);

                continue;
            }

            $subjectsResult = $this->judicialService->fetchSubjectsByProcess($apiProcessId);
            if (! $subjectsResult->isSuccessful) {
                Log::channel($channel)->error('ProcessSyncService: failed to fetch sujetos', [
                    'process_number' => $processNumber,
                    'process_id' => $process->id,
                ]);

                continue;
            }

            $this->syncActuaciones($process, $actionsResult->data, $notify);
            $this->syncSujetos($process, $subjectsResult->data);
        }
    }
}

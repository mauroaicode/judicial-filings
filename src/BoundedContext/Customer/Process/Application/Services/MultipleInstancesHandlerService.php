<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Services;

use Core\BoundedContext\Customer\Process\Application\Actions\{
    CreateOrUpdateProcessUseCase,
    HandleMultipleInstancesNotificationUseCase
};
use Core\BoundedContext\Customer\Process\Domain\Repositories\{
    ProcessRepositoryInterface,
    OrganizationNotificationRepositoryInterface
};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Core\Shared\Domain\Enums\NotificationType;
use Core\Shared\Infrastructure\Services\JudicialBranchConsultService;
use Core\BoundedContext\Customer\Process\Application\Traits\ProcessCompleteDataTrait;

readonly class MultipleInstancesHandlerService
{
    use ProcessCompleteDataTrait;

    public function __construct(
        private ProcessRepositoryInterface $processRepository,
        private JudicialBranchConsultService $judicialService,
        private CreateOrUpdateProcessUseCase $createOrUpdateProcessUseCase,
        private OrganizationNotificationRepositoryInterface $organizationNotificationRepository,
        private HandleMultipleInstancesNotificationUseCase $handleMultipleInstancesNotificationUseCase,

    ) {}

    /**
     * Maneja la lógica principal para procesos con múltiples instancias
     */
    public function handle(string $filingNumber, array $processes, Collection $interestedOrganizations): void
    {
        Log::channel('judicial_process_chunk_job')->info("🚀 INICIANDO MANEJO DE MÚLTIPLES INSTANCIAS para radicado {$filingNumber}", [
            'filing_number' => $filingNumber,
            'processes_count' => count($processes),
            'organizations_count' => $interestedOrganizations->count(),
            'organization_ids' => $interestedOrganizations->pluck('id')->toArray()
        ]);

        if ($interestedOrganizations->isEmpty()) {
            Log::channel('judicial_process_chunk_job')->info("No hay organizaciones interesadas para el radicado {$filingNumber}");
            return;
        }

        $existingNotification = $this->organizationNotificationRepository->hasAlreadyNotifiedMultipleInstances(
            $filingNumber,
            NotificationType::MULTIPLE_INSTANCE->value
        );

        if (!$existingNotification) {
            Log::channel('judicial_process_chunk_job')->info("PRIMERA DETECCIÓN: Notificando múltiples instancias para radicado {$filingNumber} a " . $interestedOrganizations->count() . " organizaciones");

            $this->processMultipleInstances($filingNumber, $processes, $interestedOrganizations);
            $this->handleMultipleInstancesNotification($filingNumber, $interestedOrganizations);
        } else {
            $this->checkForNewInstances($filingNumber, $processes, $interestedOrganizations);
        }
    }

    /**
     * Verifica si hay nuevas instancias y envía notificación si es necesario
     */
    private function checkForNewInstances(string $filingNumber, array $processes, Collection $interestedOrganizations): void
    {
        $existingProcessIds = $this->processRepository->findByProcessNumber($filingNumber)->pluck('process_id')->toArray();
        $newApiProcessIds = collect($processes)->pluck('idProceso')->toArray();

        $newInstances = array_diff($newApiProcessIds, $existingProcessIds);

        if (!empty($newInstances)) {
            Log::channel('judicial_process_chunk_job')->warning("NUEVAS INSTANCIAS DETECTADAS para el radicado {$filingNumber}: " . implode(', ', $newInstances));
            Log::channel('judicial_process_chunk_job')->info("Enviando nueva notificación por nuevas instancias detectadas");

            $this->processMultipleInstances($filingNumber, $processes, $interestedOrganizations);
            $this->handleMultipleInstancesNotification($filingNumber, $interestedOrganizations);
        } else {
            Log::channel('judicial_process_chunk_job')->info("Radicado {$filingNumber}: Ya se notificó sobre múltiples instancias. No hay nuevas instancias. No se envía nueva notificación.");
        }
    }

    /**
     * Procesa las múltiples instancias del radicado
     */
    private function processMultipleInstances(string $filingNumber, array $processes, Collection $interestedOrganizations): void
    {
        $createdProcesses = [];

        foreach ($processes as $processBasic) {
            $unifiedProcess = $this->getCompleteProcessData($processBasic, $filingNumber, $this->judicialService);

            if ($unifiedProcess) {
                $process = $this->createOrUpdateProcessUseCase->__invoke($unifiedProcess);
                $createdProcesses[] = $process;
            }
        }

        if (!empty($createdProcesses)) {
            $firstProcess = $createdProcesses[0];
            $organizationIds = $interestedOrganizations->pluck('id')->toArray();

            Log::channel('judicial_process_chunk_job')->info("Asignando " . count($organizationIds) . " organizaciones al proceso {$firstProcess->process_id}");

            $this->processRepository->assignOrganizationsToProcess($firstProcess->id, $organizationIds);

            Log::channel('judicial_process_chunk_job')->info("✅ Proceso {$firstProcess->process_id} asignado exitosamente a " . count($organizationIds) . " organizaciones");
        } else {
            Log::channel('judicial_process_chunk_job')->warning("⚠️ No se pudieron crear procesos para el radicado {$filingNumber}");
        }

        // Marcar el radicado como que tiene múltiples instancias
        $this->processRepository->updateProcessesByProcessNumber($filingNumber, ['has_multiple_instances' => true]);
    }

    /**
     * Maneja la notificación de múltiples instancias
     */
    private function handleMultipleInstancesNotification(string $filingNumber, Collection $interestedOrganizations): void
    {
        try {
            Log::channel('judicial_process_chunk_job')->info("Enviando notificación de múltiples instancias para radicado {$filingNumber}");

            $firstProcess = $this->processRepository->findByProcessNumber($filingNumber)->first();

            if ($firstProcess) {
                $this->handleMultipleInstancesNotificationUseCase->__invoke($firstProcess, $interestedOrganizations, $filingNumber);
            }

        } catch (\Exception $e) {
            Log::channel('judicial_process_chunk_job')->error("Error manejando notificación de múltiples instancias para radicado {$filingNumber}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

}

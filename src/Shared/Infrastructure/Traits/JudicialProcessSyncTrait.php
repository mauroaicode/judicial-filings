<?php

namespace Core\Shared\Infrastructure\Traits;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\Shared\Domain\Repositories\OrganizationRepositoryInterface;
use Core\Shared\Infrastructure\Services\JudicialBranchConsultService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

trait JudicialProcessSyncTrait
{
    /**
     * Sincroniza procesos judiciales según los parámetros proporcionados
     * @throws Exception
     */
    protected function syncJudicialProcesses(
        JudicialBranchConsultService $judicialService,
        OrganizationRepositoryInterface $organizationRepository,
        ProcessRepositoryInterface $processRepository,
        ?string $organizationSlug = null,
        ?string $filingNumber = null
    ): void {

        try {

            $filingNumbers = $this->getFilingNumbersToProcess(
                $organizationRepository,
                $processRepository,
                $organizationSlug,
                $filingNumber
            );

            if ($filingNumbers->isEmpty()) {
                Log::channel('judicial_process_sync_job')->info('No se encontraron radicados para procesar.');
                return;
            }

            Log::channel('judicial_process_sync_job')->info("Procesando {$filingNumbers->count()} radicados únicos...");

            $filingNumbers->chunk(100)->each(function ($chunk, $index) use ($judicialService, $processRepository) {
                Log::channel('judicial_process_sync_job')->info("Procesando lote " . ($index + 1) . " con {$chunk->count()} radicados");

                foreach ($chunk as $filingNumber) {
                    $this->syncProcessWithAPI($judicialService, $filingNumber, $processRepository);
                }
            });

            Log::channel('judicial_process_sync_job')->info('Sincronización de procesos judiciales completada exitosamente.');

        } catch (Exception $e) {
            Log::channel('judicial_process_sync_job')->error('Error en sincronización judicial: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'organization_slug' => $organizationSlug,
                'filing_number' => $filingNumber,
                'filing_count' => $filingNumbers->count(),
            ]);

            throw $e;
        }
    }

    /**
     * Obtiene los radicados únicos según los parámetros
     */
    protected function getFilingNumbersToProcess(
        OrganizationRepositoryInterface $organizationRepository,
        ProcessRepositoryInterface $processRepository,
        ?string $organizationSlug = null,
        ?string $filingNumber = null
    ): Collection {

        if ($filingNumber) {
            return collect([$filingNumber]);
        }

        if ($organizationSlug) {
            return $this->getFilingsByOrganization($organizationRepository, $processRepository, $organizationSlug);
        }

        Log::channel('judicial_process_sync_job')->info('Consultando todos los radicados con organizaciones activas...');
        return $processRepository->getAllUniqueProcessNumbersWithActiveOrganizations();
    }

    /**
     * Obtiene los radicados únicos de una organización específica
     */
    protected function getFilingsByOrganization(
        OrganizationRepositoryInterface $organizationRepository,
        ProcessRepositoryInterface $processRepository,
        string $organizationSlug
    ): Collection {
        $organization = $organizationRepository->findSlug($organizationSlug);

        if (!$organization) {
            Log::channel('judicial_process_sync_job')->warning("No se encontró la organización con el slug: {$organizationSlug}");
            return collect();
        }

        $processes = $processRepository->findByOrganization($organization->id);
        return $processes->pluck('process_number')->unique();
    }

    /**
     * Sincroniza un proceso específico con la API judicial
     */
    protected function syncProcessWithAPI(JudicialBranchConsultService $judicialService, string $filingNumber, ProcessRepositoryInterface $processRepository): void
    {
        try {
            Log::channel('judicial_process_sync_job')->info("Sincronizando radicado: {$filingNumber}");

            // 1. Obtener lista de procesos
            $responseApiResponse = $judicialService->fetchProcesses($filingNumber);

            if (!$responseApiResponse->isSuccessful) {
                Log::channel('judicial_process_sync_job')->error("Error al consultar API judicial para radicado: {$filingNumber}");
                return;
            }

            $processes = $responseApiResponse->data;

            if (empty($processes)) {
                Log::channel('judicial_process_sync_job')->warning("No se encontraron procesos en la API para radicado: {$filingNumber}");
                return;
            }

            // 2. Procesar cada proceso con su detalle
            foreach ($processes as $processBasic) {
                $unifiedProcess = $this->getUnifiedProcessData($judicialService, $processBasic, $filingNumber);

                if ($unifiedProcess) {
                    if (count($processes) > 1) {
                        $this->handleMultipleInstances($filingNumber, $unifiedProcess, $processRepository);
                    } else {
                        $this->handleSingleProcess($filingNumber, $unifiedProcess, $processRepository);
                    }
                }
            }

            // 3. Sincronizar actuaciones para cada proceso
            $this->syncProcessActions($judicialService, $processes);

        } catch (Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error sincronizando radicado {$filingNumber}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'filing_number' => $filingNumber,
                'api_response' => $responseApiResponse ?? null,
            ]);
        }
    }

    /**
     * Unifica los datos del proceso básico y el detalle en una sola variable
     */
    protected function getUnifiedProcessData(JudicialBranchConsultService $judicialService, array $processBasic, string $filingNumber): ?array
    {
        try {

            $responseProcessDetail = $judicialService->fetchDetailProcess($processBasic['idProceso']);

            if (!$responseProcessDetail->isSuccessful) {
                Log::channel('judicial_process_sync_job')->warning("No se pudo obtener detalle para proceso: {$processBasic['idProceso']}");
                return null;
            }

            $processDetail = $responseProcessDetail->data;

            return [

                'process_id' => $processBasic['idProceso'],
                'process_number' => $filingNumber,
                'fechaProceso' => $processBasic['fechaProceso'],
                'fechaUltimaActuacion' => $processBasic['fechaUltimaActuacion'],
                'despacho' => $processBasic['despacho'],
                'departamento' => $processBasic['departamento'],
                'sujetosProcesales' => $processBasic['sujetosProcesales'],
                'esPrivado' => $processBasic['esPrivado'],

                // Datos del detalle del proceso
                'tipoProceso' => $processDetail['tipoProceso'] ?? 'N/A',
                'claseProceso' => $processDetail['claseProceso'] ?? 'N/A',
                'subclaseProceso' => $processDetail['subclaseProceso'] ?? null,
                'ubicacion' => $processDetail['ubicacion'] ?? null,
                'contenidoRadicacion' => $processDetail['contenidoRadicacion'] ?? null,
                'ponente' => $processDetail['ponente'] ?? null,
                'codDespachoCompleto' => $processDetail['codDespachoCompleto'] ?? null,

                'last_api_update' => now(),
            ];

        } catch (Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error unificando datos del proceso {$processBasic['idProceso']}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'process_id' => $processBasic['idProceso'],
                'filing_number' => $filingNumber,
            ]);
            return null;
        }
    }

    /**
     * Maneja procesos con múltiples instancias
     */
    protected function handleMultipleInstances(string $filingNumber, array $unifiedProcess, ProcessRepositoryInterface $processRepository): void
    {
        Log::channel('judicial_process_sync_job')->warning("Radicado {$filingNumber} tiene múltiples instancias");

        // Obtener las organizaciones interesadas en este número de proceso
        $interestedOrganizations = $processRepository->getOrganizationsByProcessNumber($filingNumber);
        
        if ($interestedOrganizations->isEmpty()) {
            Log::channel('judicial_process_sync_job')->warning("No se encontraron organizaciones interesadas en el radicado {$filingNumber}");
            return;
        }

        Log::channel('judicial_process_sync_job')->info("Organizaciones interesadas en radicado {$filingNumber}: " . $interestedOrganizations->pluck('name')->implode(', '));

        // Crear o actualizar la instancia del proceso
        $this->createOrUpdateProcessInstance($unifiedProcess, $processRepository);

        // Obtener el proceso recién creado/actualizado
        $process = $processRepository->findByProcessId($unifiedProcess['process_id']);
        
        if ($process) {
            // Asignar las organizaciones interesadas a esta nueva instancia
            $organizationIds = $interestedOrganizations->pluck('id')->toArray();
            $processRepository->assignOrganizationsToProcess($process->id, $organizationIds);
            
            Log::channel('judicial_process_sync_job')->info("Proceso {$unifiedProcess['process_id']} asignado a " . count($organizationIds) . " organizaciones");
        }

        // Marcar que tiene múltiples instancias
        $processRepository->updateProcessesByProcessNumber($filingNumber, ['has_multiple_instances' => true]);
    }

    /**
     * Maneja procesos únicos
     */
    protected function handleSingleProcess(string $filingNumber, array $unifiedProcess, ProcessRepositoryInterface $processRepository): void
    {
        Log::channel('judicial_process_sync_job')->info("Radicado {$filingNumber} es proceso único");
        $this->createOrUpdateProcessInstance($unifiedProcess, $processRepository);
    }

    /**
     * Crea o actualiza una instancia de proceso
     */
    protected function createOrUpdateProcessInstance(array $unifiedProcess, ProcessRepositoryInterface $processRepository): void
    {
        // Mapear datos unificados a la estructura del modelo
        $processData = [
            'process_id' => $unifiedProcess['process_id'],
            'process_number' => $unifiedProcess['process_number'],
            'court' => $unifiedProcess['despacho'],
            'department' => $unifiedProcess['departamento'],
            'process_type' => $unifiedProcess['tipoProceso'],
            'process_class' => $unifiedProcess['claseProceso'],
            'subclass_process' => $unifiedProcess['subclaseProceso'],
            'litigants' => $unifiedProcess['sujetosProcesales'],
            'process_date' => $unifiedProcess['fechaProceso'],
            'last_activity_date' => $unifiedProcess['fechaUltimaActuacion'],
            'location' => $unifiedProcess['ubicacion'],
            'filing_content' => $unifiedProcess['contenidoRadicacion'],
            'is_private' => $unifiedProcess['esPrivado'],
            'last_api_update' => $unifiedProcess['last_api_update'],
        ];

        // Usar el repositorio para crear o actualizar
        $process = $processRepository->createOrUpdateProcess($processData);

        if ($process->wasRecentlyCreated) {
            Log::channel('judicial_process_sync_job')->info("Nuevo proceso creado: {$unifiedProcess['process_id']}");
        } else {
            Log::channel('judicial_process_sync_job')->info("Proceso actualizado: {$unifiedProcess['process_id']}");
        }
    }

    /**
     * Sincroniza las actuaciones de los procesos
     */
    protected function syncProcessActions(JudicialBranchConsultService $judicialService, array $processes): void
    {
        foreach ($processes as $proceso) {
            try {
                $actionsResponse = $judicialService->fetchActionByProcess($proceso['idProceso']);

                if ($actionsResponse->isSuccessful && !empty($actionsResponse->data)) {
                    Log::channel('judicial_process_sync_job')->info("Sincronizando actuaciones para proceso {$proceso['idProceso']}");

                    // TODO: Implementar sincronización de actuaciones
                    // ProcessActionSyncJob::dispatch($proceso['idProceso'], $actionsResponse->data);
                }
            } catch (Exception $e) {
                Log::channel('judicial_process_sync_job')->error("Error sincronizando actuaciones para proceso {$proceso['idProceso']}: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'process_id' => $proceso['idProceso'],
                    'process_number' => $proceso['llaveProceso'] ?? 'N/A',
                ]);
            }
        }
    }
}

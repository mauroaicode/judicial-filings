<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Core\Shared\Infrastructure\Services\JudicialBranchConsultService;
use Core\BoundedContext\Customer\Process\Application\Traits\ProcessCompleteDataTrait;
use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\BoundedContext\Customer\Process\Application\Actions\CreateOrUpdateProcessUseCase;


class JudicialProcessChunkService
{
    use ProcessCompleteDataTrait;

    public function __construct(
        private readonly JudicialBranchConsultService $judicialService,
        private readonly ProcessRepositoryInterface   $processRepository,
        private readonly CreateOrUpdateProcessUseCase $createOrUpdateProcessUseCase,
        private readonly MultipleInstancesHandlerService $multipleInstancesHandlerService
    ) {}

    /**
     * Procesa un chunk de radicados
     */
    public function handle(array $filingNumbers): void
    {
        $chunkSize = count($filingNumbers);

        foreach ($filingNumbers as $index => $filingNumber) {
            try {
                Log::channel('judicial_process_chunk_job')->info("Procesando radicado " . ($index + 1) . "/{$chunkSize}: {$filingNumber}");

                $this->syncProcessWithAPI($filingNumber);

            } catch (\Exception $e) {
                Log::channel('judicial_process_chunk_job')->error("Error procesando radicado {$filingNumber}: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                continue;
            }
        }
    }

    /**
     * Sincroniza un proceso específico con la API judicial
     */
    private function syncProcessWithAPI(string $filingNumber): void
    {
        try {
            Log::channel('judicial_process_chunk_job')->info("Sincronizando radicado con la API DE LA RAMA JUDICIAL: {$filingNumber}");

            $responseApiResponse = $this->judicialService->fetchProcesses($filingNumber);

            if (!$responseApiResponse->isSuccessful) {
                Log::channel('judicial_process_chunk_job')->error("Error al consultar API DE LA RAMA JUDICIAL para radicado: {$filingNumber}");
                return;
            }

            $processes = $responseApiResponse->data;

            if (empty($processes)) {
                Log::channel('judicial_process_chunk_job')->warning("No se encontraron procesos en la API DE LA RAMA JUDICIAL para radicado: {$filingNumber}");
                return;
            }

            $hasMultipleInstances = count($processes) > 1;

            if ($hasMultipleInstances) {

                Log::channel('judicial_process_chunk_job')->warning("Radicado {$filingNumber} tiene múltiples instancias");
                $interestedOrganizations = $this->processRepository->getOrganizationsByProcessNumber($filingNumber);


                $this->multipleInstancesHandlerService->handle($filingNumber, $processes, $interestedOrganizations);
            }

            // 2. Procesar cada proceso con su detalle
            foreach ($processes as $processBasic) {

                $unifiedProcess = $this->getCompleteProcessData($processBasic, $filingNumber, $this->judicialService);

                if ($unifiedProcess) {

                    if (!$hasMultipleInstances) {
                        Log::channel('judicial_process_chunk_job')->info("Radicado {$filingNumber} es proceso único");
                    }
                    $this->processIndividualProcess($unifiedProcess);
                }
            }

            // 3. Sincronizar actuaciones para cada proceso
            $this->syncProcessActions($processes);

        } catch (Exception $e) {
            Log::channel('judicial_process_chunk_job')->error("Error sincronizando radicado {$filingNumber}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'filing_number' => $filingNumber,
                'api_response' => $responseApiResponse ?? null,
            ]);
        }
    }



    /**
     * Procesa un proceso individual
     */
    private function processIndividualProcess(array $unifiedProcess): void
    {
        $this->createOrUpdateProcessUseCase->__invoke($unifiedProcess);
    }


    /**
     * Sincroniza las actuaciones de los procesos
     */
    private function syncProcessActions(array $processes): void
    {
        foreach ($processes as $proceso) {
            try {
                $actionsResponse = $this->judicialService->fetchActionByProcess($proceso['idProceso']);

                if ($actionsResponse->isSuccessful && !empty($actionsResponse->data)) {
                    Log::channel('judicial_process_chunk_job')->info("Sincronizando actuaciones para proceso {$proceso['idProceso']}");

                    // TODO: Implementar sincronización de actuaciones
                    // ProcessActionSyncJob::dispatch($proceso['idProceso'], $actionsResponse->data);
                }
            } catch (Exception $e) {
                Log::channel('judicial_process_chunk_job')->error("Error sincronizando actuaciones para proceso {$proceso['idProceso']}: " . $e->getMessage(), [
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

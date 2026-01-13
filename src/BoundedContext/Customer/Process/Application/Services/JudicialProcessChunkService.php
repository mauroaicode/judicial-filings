<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Core\Shared\Infrastructure\Services\JudicialBranchConsultService;
use Core\BoundedContext\Customer\Process\Application\Traits\ProcessCompleteDataTrait;
use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\BoundedContext\Customer\Process\Application\Actions\CreateOrUpdateProcessUseCase;
use Core\Shared\Infrastructure\Jobs\ProcessActionSyncJob;


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
        foreach ($filingNumbers as $filingNumber) {
            try {

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

                $interestedOrganizations = $this->processRepository->getOrganizationsByProcessNumber($filingNumber);

                $this->multipleInstancesHandlerService->handle($filingNumber, $processes, $interestedOrganizations);

            } else {

                foreach ($processes as $processBasic) {

                    $unifiedProcess = $this->getCompleteProcessData($processBasic, $filingNumber, $this->judicialService);

                    if ($unifiedProcess) {
                        $this->processIndividualProcess($unifiedProcess);
                    }
                }
            }


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
     * Sincroniza las actuaciones de los procesos en chunks para evitar sobrecarga
     */
    private function syncProcessActions(array $processes): void
    {
        if (empty($processes)) {
            return;
        }

        $chunkSize = 5;
        $chunks = array_chunk($processes, $chunkSize);

        foreach ($chunks as $index => $chunk) {

            $delay = $index * 5;

            ProcessActionSyncJob::dispatch($chunk)->delay(now()->addSeconds($delay));
        }

        Log::channel('judicial_process_chunk_job')->info("Dispatched " . count($chunks) . " chunks of process actions with delays");
    }
}

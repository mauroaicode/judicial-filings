<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Services;


use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Core\Shared\Infrastructure\Jobs\ProcessChunkJob;
use Core\BoundedContext\Customer\Process\Application\Actions\GetFilingNumbersToProcessUseCase;

readonly class JudicialProcessSyncService
{
    public function __construct(
        private GetFilingNumbersToProcessUseCase $getFilingNumbersToProcessUseCase
    ) {}

    /**
     * Sincroniza procesos judiciales según los parámetros proporcionados
     * @throws Exception
     */
    public function syncJudicialProcesses(
        ?string $organizationSlug = null,
        ?string $filingNumber = null
    ): void {

        try {

            $filingNumbers = $this->getFilingNumbersToProcess(
                $organizationSlug,
                $filingNumber
            );

            if ($filingNumbers->isEmpty()) {
                Log::channel('judicial_process_sync_job')->info('No se encontraron radicados para procesar.');
                return;
            }

            Log::channel('judicial_process_sync_job')->info("Total de radicados a procesar: {$filingNumbers->count()}");


            $filingNumbers->chunk(100)->each(function ($chunk, $index) {
                $chunkArray = $chunk->toArray();
                Log::channel('judicial_process_sync_job')->info("Despachando lote " . ($index + 1) . " con " . count($chunkArray) . " radicados");

                ProcessChunkJob::dispatch($chunkArray)->onQueue(config('queue.queues.process-chunk.queue'));
            });

            Log::channel('judicial_process_sync_job')->info('Todos los chunks han sido despachados exitosamente.');

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
    private function getFilingNumbersToProcess(
        ?string $organizationSlug = null,
        ?string $filingNumber = null
    ): Collection {

        if ($filingNumber) {
            return collect([$filingNumber]);
        }

        if ($organizationSlug) {
            return $this->getFilingsByOrganization($organizationSlug);
        }

        return $this->getFilingNumbersToProcessUseCase->getAllUniqueProcessNumbersWithActiveOrganizations();
    }

    /**
     * Obtiene los radicados únicos de una organización específica
     */
    private function getFilingsByOrganization(string $organizationSlug): Collection {
        return $this->getFilingNumbersToProcessUseCase->getFilingsByOrganization($organizationSlug);
    }
}

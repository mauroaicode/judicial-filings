<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Core\Shared\Infrastructure\Jobs\ProcessChunkJob;
use Core\BoundedContext\Customer\Process\Application\Actions\GetFilingNumbersToProcessUseCase;

/**
 * Service responsible for synchronizing judicial processes by dispatching jobs
 * to search filing numbers in the judicial branch API. It can process specific
 * filing numbers, all filings from an organization, or all active filings.
 */
readonly class JudicialProcessSyncService
{

    public function __construct(
        private GetFilingNumbersToProcessUseCase $getFilingNumbersToProcessUseCase
    ){
    }

    /**
     * Synchronize judicial processes based on provided parameters
     *
     * @param string|null $organizationSlug Organization slug to filter processes
     * @param string|null $filingNumber Specific filing number to process
     * @return void
     * @throws Exception
     */
    public function syncJudicialProcesses(
        ?string $organizationSlug = null,
        ?string $filingNumber = null
    ): void
    {
        $filingNumbers = collect();

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

            $filingNumbers->chunk(config('filing.number_processed_in_job'))->each(function ($chunk, $index) {
                $chunkArray = $chunk->toArray();
                Log::channel('judicial_process_sync_job')->info("Despachando lote " . ($index + 1) . " con " . count($chunkArray) . " radicados");

                ProcessChunkJob::dispatch($chunkArray);
            });

            Log::channel('judicial_process_sync_job')->info('Todos los chunks radicados han sido despachados exitosamente.');

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
     * Get unique filing numbers to process based on parameters
     *
     * @param string|null $organizationSlug Organization slug to filter by
     * @param string|null $filingNumber Specific filing number to process
     * @return Collection Collection of filing numbers to process
     */
    private function getFilingNumbersToProcess(
        ?string $organizationSlug = null,
        ?string $filingNumber = null
    ): Collection
    {

        if ($filingNumber) {
            return collect([$filingNumber]);
        }

        if ($organizationSlug) {
            return $this->getFilingsByOrganization($organizationSlug);
        }

        return $this->getFilingNumbersToProcessUseCase->getAllUniqueProcessNumbersWithActiveOrganizations();
    }

    /**
     * Get unique filing numbers for a specific organization
     *
     * @param string $organizationSlug Organization slug to get filings for
     * @return Collection Collection of filing numbers for the organization
     */
    private function getFilingsByOrganization(string $organizationSlug): Collection
    {
        return $this->getFilingNumbersToProcessUseCase->getFilingsByOrganization($organizationSlug);
    }
}

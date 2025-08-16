<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Jobs;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\Shared\Domain\Repositories\OrganizationRepositoryInterface;
use Core\Shared\Infrastructure\Traits\JudicialProcessSyncTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, JudicialProcessSyncTrait;

    public $timeout = 300;
    public $tries = 3;

    public function __construct(private readonly ?string $organizationSlug, private readonly ?string $filingNumber) {
        $this->onQueue(config('queue.queues.process-sync.queue'));
    }

    /**
     * @throws Exception
     */
    public function handle(
        OrganizationRepositoryInterface $organizationRepository,
        ProcessRepositoryInterface $processRepository
    ): void {
        try {
            Log::channel('judicial_process_sync_job')->info('Iniciando sincronización judicial');

            $filingNumbers = $this->getFilingNumbersToProcess(
                $organizationRepository,
                $processRepository,
                $this->organizationSlug,
                $this->filingNumber
            );

            if ($filingNumbers->isEmpty()) {
                Log::channel('judicial_process_sync_job')->info('No se encontraron radicados para procesar.');
                return;
            }

            $totalFilingNumbers = $filingNumbers->count();
            Log::channel('judicial_process_sync_job')->info("Total de radicados a procesar: {$totalFilingNumbers}");


            $filingNumbers->chunk(100)->each(function ($chunk, $index) use ($totalFilingNumbers) {
                $chunkNumber = $index + 1;
                $totalChunks = ceil($totalFilingNumbers / 100);

                Log::channel('judicial_process_sync_job')->info("Despachando lote {$chunkNumber}/{$totalChunks} con {$chunk->count()} radicados");

                ProcessChunkJob::dispatch($chunk->toArray());
            });

            Log::channel('judicial_process_sync_job')->info("Todos los lotes han sido despachados en la cola 'judicial-chunk'");
            Log::channel('judicial_process_sync_job')->info('Sincronización judicial completada exitosamente.');

        } catch (Exception $e) {
            Log::channel('judicial_process_sync_job')->error('Error en sincronización judicial: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

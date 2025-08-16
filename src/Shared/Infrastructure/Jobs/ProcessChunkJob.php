<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Jobs;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\Shared\Infrastructure\Services\JudicialBranchConsultService;
use Core\Shared\Infrastructure\Traits\JudicialProcessSyncTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, JudicialProcessSyncTrait;

    public $timeout = 300;
    public $tries = 3;

    public function __construct(private readonly array $filingNumbers) {
        $this->onQueue(config('queue.queues.process-chunk.queue'));
    }

    public function handle(
        JudicialBranchConsultService $judicialService,
        ProcessRepositoryInterface $processRepository
    ): void {

        $chunkSize = count($this->filingNumbers);
        Log::channel('judicial_process_sync_job')->info("Procesando lote con {$chunkSize} radicados");

        foreach ($this->filingNumbers as $index => $filingNumber) {
            try {
                Log::channel('judicial_process_sync_job')->info("Procesando radicado " . ($index + 1) . "/{$chunkSize}: {$filingNumber}");

                $this->syncProcessWithAPI($judicialService, $filingNumber, $processRepository);

                Log::channel('judicial_process_sync_job')->info("Radicado {$filingNumber} procesado exitosamente");
            } catch (\Exception $e) {
                Log::channel('judicial_process_sync_job')->error("Error procesando radicado {$filingNumber}: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                continue;
            }
        }

        Log::channel('judicial_process_sync_job')->info("Lote completado. {$chunkSize} radicados procesados");
    }
}

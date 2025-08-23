<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Jobs;

use Exception;
use Illuminate\Queue\{
    InteractsWithQueue,
    SerializesModels
};
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Core\BoundedContext\Customer\Process\Application\Services\JudicialProcessSyncService;

class ProcessSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    public function __construct(
        private readonly ?string $organizationSlug = null,
        private readonly ?string $filingNumber = null
    ) {
        $this->onQueue(config('queue.queues.process-sync.queue'));
    }

    /**
     * @throws Exception
     */
    public function handle(JudicialProcessSyncService $judicialProcessSyncService): void
    {
        try {
            Log::channel('judicial_process_sync_job')->info('Iniciando sincronización de procesos judiciales...', [
                'organization_slug' => $this->organizationSlug,
                'filing_number' => $this->filingNumber,
            ]);

            $judicialProcessSyncService->syncJudicialProcesses(
                $this->organizationSlug,
                $this->filingNumber
            );

            Log::channel('judicial_process_sync_job')->info('Sincronización completada exitosamente.');

        } catch (Exception $e) {
            Log::channel('judicial_process_sync_job')->error('Error en ProcessSyncJob: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'organization_slug' => $this->organizationSlug,
                'filing_number' => $this->filingNumber,
            ]);

            throw $e;
        }
    }
}

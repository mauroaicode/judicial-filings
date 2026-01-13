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
use Core\BoundedContext\Customer\Process\Application\Services\ProcessActionSyncService;

class ProcessActionSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    public function __construct(
        private readonly array $processes
    ) {
        $this->onQueue(config('queue.queues.process-action-sync.queue', 'process-action-sync'));
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(ProcessActionSyncService $processActionSyncService): void
    {
        try {

            $processActionSyncService->syncProcessActions($this->processes);

        } catch (Exception $e) {

            Log::channel('judicial_process_sync_job')->error('Error en ProcessActionSyncJob: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'processes_count' => count($this->processes),
                'process_ids' => array_column($this->processes, 'idProceso')
            ]);

            throw $e;
        }
    }
}

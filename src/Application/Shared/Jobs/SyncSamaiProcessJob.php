<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Throwable;

/**
 * Sincroniza un radicado de SAMAI durante el cron diario.
 *
 * Llama a ProcessSyncService::syncSamaiByProcessNumber() para actualizar
 * actuaciones y sujetos procesales del radicado.
 *
 * Análogo a SyncProcessJob para Rama Judicial.
 */
class SyncSamaiProcessJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var int */
    public $timeout = 120;

    /**
     * Espera entre reintentos: 60s → 120s → 300s.
     * Da tiempo al servidor SAMAI a recuperarse si estaba saturado o lento.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 120, 300];
    }

    public function __construct(
        public readonly string $processNumber,
    ) {
        $config = config('judicial-sync.jobs.sync_samai_process', []);
        $this->queue = $config['queue'] ?? 'samai-sync';
        $this->tries = $config['tries'] ?? 3;
        $this->timeout = $config['timeout'] ?? 120;

        if (! empty($config['connection'])) {
            $this->connection = $config['connection'];
        }
    }

    /**
     * @throws Throwable
     */
    public function handle(ProcessSyncService $syncService): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        try {
            $syncService->syncSamaiByProcessNumber($this->processNumber);

        } catch (Throwable $e) {
            Log::channel($channel)->error('SyncSamaiProcessJob failed', [
                'process_number' => $this->processNumber,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

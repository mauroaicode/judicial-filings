<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\Process\ProcessSourceFallbackService;
use Throwable;

/**
 * Intenta migrar un radicado que se volvió privado en Rama Judicial a la siguiente
 * fuente disponible (actualmente SAMAI; TYBA en el futuro).
 *
 * Se despacha desde ProcessSyncService::discoverNewProcesses() cuando detecta
 * que un proceso cambió de is_private = false → true durante el cron diario.
 *
 * El job usa la cola "judicial-sync" para no bloquear la cola principal.
 * Los reintentos están limitados: si SAMAI tampoco lo tiene, el proceso
 * simplemente permanece privado hasta el próximo ciclo.
 */
class MigratePrivateProcessSourceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 2;

    /** @var int */
    public $timeout = 120;

    public function __construct(
        public readonly string $processNumber,
    ) {
        $this->queue = config('judicial-sync.jobs.migrate_private_source.queue', 'judicial-sync');
    }

    public function handle(ProcessSourceFallbackService $fallbackService): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        Log::channel($channel)->info('MigratePrivateProcessSourceJob: starting migration attempt', [
            'process_number' => $this->processNumber,
            'attempt' => $this->attempts(),
        ]);

        try {
            $migrated = $fallbackService->tryMigratePrivateJudicialToSamai($this->processNumber);

            Log::channel($channel)->info('MigratePrivateProcessSourceJob: completed', [
                'process_number' => $this->processNumber,
                'migrated' => $migrated,
            ]);

            // No se despacha digest aquí: las OrganizationNotification quedan pendientes
            // (is_email_notified=false) y salen en el próximo consolidado del sync diario.

        } catch (Throwable $e) {
            Log::channel($channel)->error('MigratePrivateProcessSourceJob: failed', [
                'process_number' => $this->processNumber,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

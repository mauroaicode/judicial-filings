<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Core\Shared\Infrastructure\Jobs\ProcessSyncJob;


class SyncOrganizationFilingsCommand extends Command
{
    protected $signature = 'organization:sync-process
                            {--organization= : Slug de la organización específica a procesar}
                            {--filing= : Número de radicado específico a procesar}';

    protected $description = 'Obtiene los radicados de una organización específica O procesa un radicado específico O procesa todos los radicados de todas las organizaciones.';

    public function handle(): void
    {
        $organizationSlug = $this->option('organization');
        $filingNumber = $this->option('filing');

        if ($organizationSlug && $filingNumber) {
            $this->error('Error: Solo puedes especificar una organización O un número de radicado, no ambos.');
            $this->error('Uso:');
            $this->error('  php artisan organization:sync-process --organization=slug-org');
            $this->error('  php artisan organization:sync-process --filing=numero-radicado');
            $this->error('  php artisan organization:sync-process (procesa todos los radicados)');
            return;
        }

        ProcessSyncJob::dispatch($organizationSlug, $filingNumber);

        $queueSyncProcess = config('queue.queues.process-sync.queue');

        $this->info("Job de sincronización judicial despachado en la cola {$queueSyncProcess}'.");
    }
}

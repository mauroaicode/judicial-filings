<?php

namespace App\Console\Commands;

use Core\Shared\Infrastructure\Jobs\ProcessSyncJob;
use Illuminate\Console\Command;

class SyncOrganizationFilingsCommand extends Command
{
    protected $signature = 'organization:sync-filings
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
            $this->error('  php artisan organization:sync-filings --organization=slug-org');
            $this->error('  php artisan organization:sync-filings --filing=numero-radicado');
            $this->error('  php artisan organization:sync-filings (procesa todos los radicados)');
            return;
        }

        ProcessSyncJob::dispatch($organizationSlug, $filingNumber);

        $this->info('Job de sincronización judicial despachado en la cola "judicial-sync".');
        $this->info('Proceso completado.');
    }
}

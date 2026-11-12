<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

/**
 * Encola el sync de una organización fuera del request HTTP.
 * Insertar ~1000 jobs en un POST de admin cortaba el lote
 * ("Request ended before the batch was queued") y dejaba radicados sin consultar.
 */
class QueueOrganizationJudicialSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $organizationId,
        public readonly ?string $radicadoFilter = null,
    ) {
        $config = config('judicial-sync.jobs.sync_process', []);
        $this->queue = $config['queue'] ?? 'judicial-sync';
        if (! empty($config['connection'])) {
            $this->connection = $config['connection'];
        }
    }

    public function uniqueId(): string
    {
        return $this->organizationId.'|'.($this->radicadoFilter ?? '*');
    }

    public function handle(): void
    {
        $parameters = ['organization' => $this->organizationId];

        if ($this->radicadoFilter !== null && $this->radicadoFilter !== '') {
            $parameters['--radicado'] = $this->radicadoFilter;
        }

        $exitCode = Artisan::call('judicial:sync-processes-for-organization', $parameters);

        if ($exitCode !== ConsoleCommand::SUCCESS) {
            $this->fail(new \RuntimeException(
                'judicial:sync-processes-for-organization exited with code '.$exitCode
            ));
        }
    }
}

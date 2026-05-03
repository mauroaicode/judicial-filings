<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Services;

use Illuminate\Support\Facades\Artisan;
use Src\Application\Admin\JudicialSync\Data\AdminJudicialSyncData;
use Src\Application\Admin\JudicialSync\Resources\AdminJudicialSyncResource;
use Src\Domain\Process\Models\Process;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

readonly class AdminJudicialSyncService
{
    public function handle(AdminJudicialSyncData $data): AdminJudicialSyncResource
    {
        return $this->executeJudicialSync($data);
    }

    /**
     * Runs the Artisan command judicial:sync-processes (same options as the scheduled cron).
     */
    private function executeJudicialSync(AdminJudicialSyncData $data): AdminJudicialSyncResource
    {
        $params = [];
        if ($data->radicado !== null && $data->radicado !== '') {
            $params['--radicado'] = $data->radicado;
        }

        $exitCode = Artisan::call('judicial:sync-processes', $params);

        abort_unless(
            $exitCode === ConsoleCommand::SUCCESS,
            500,
            __('process.judicial_sync_command_failed')
        );

        $jobsCount = $this->countEligibleRadicadosForSync($data->radicado);

        if ($jobsCount === 0) {
            return AdminJudicialSyncResource::noJobs($data->radicado);
        }

        return AdminJudicialSyncResource::fromDispatch($jobsCount, $data->radicado);
    }

    /**
     * Same filters as {@see ProcessQueryBuilder::forJudicialDailySync()} but uses a query-level count (not collection).
     */
    private function countEligibleRadicadosForSync(?string $radicadoFilter): int
    {
        $query = Process::query()
            ->join('organization_processes', 'processes.id', '=', 'organization_processes.process_id')
            ->where('organization_processes.is_active', true);

        if ($radicadoFilter !== null && $radicadoFilter !== '') {
            $query->where('processes.process_number', $radicadoFilter);
        }

        return (int) $query->distinct()->count('processes.process_number');
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Services;

use Illuminate\Support\Facades\Artisan;
use Src\Application\Admin\JudicialSync\Data\AdminJudicialSyncData;
use Src\Application\Admin\JudicialSync\Resources\AdminJudicialSyncResource;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

readonly class AdminJudicialSyncService
{
    public function handle(AdminJudicialSyncData $data): AdminJudicialSyncResource
    {
        $dataSource = $data->dataSource();

        abort_unless(
            $dataSource->isExecutable(),
            422,
            __('process.sync_source_not_implemented', ['source' => $dataSource->getLabel()])
        );

        return $this->executeSync($data, $dataSource);
    }

    private function executeSync(AdminJudicialSyncData $data, JudicialSyncDataSource $dataSource): AdminJudicialSyncResource
    {
        $params = [];
        if ($data->radicado !== null && $data->radicado !== '') {
            $params['--radicado'] = $data->radicado;
        }

        $exitCode = Artisan::call($dataSource->artisanCommand(), $params);

        abort_unless(
            $exitCode === ConsoleCommand::SUCCESS,
            500,
            __('process.sync_command_failed', ['source' => $dataSource->getLabel()])
        );

        $jobsCount = $this->countEligibleRadicadosForSync($dataSource, $data->radicado);

        if ($jobsCount === 0) {
            return AdminJudicialSyncResource::noJobs($data->radicado, $dataSource);
        }

        return AdminJudicialSyncResource::fromDispatch($jobsCount, $data->radicado, $dataSource);
    }

    private function countEligibleRadicadosForSync(JudicialSyncDataSource $dataSource, ?string $radicadoFilter): int
    {
        return match ($dataSource) {
            JudicialSyncDataSource::JudicialBranch => $this->countJudicialBranchRadicados($radicadoFilter),
            JudicialSyncDataSource::Samai => $this->countSamaiRadicados($radicadoFilter),
            JudicialSyncDataSource::Tyba => 0,
        };
    }

    private function countJudicialBranchRadicados(?string $radicadoFilter): int
    {
        $query = Process::query()
            ->join('organization_processes', 'processes.id', '=', 'organization_processes.process_id')
            ->where('organization_processes.is_active', true)
            ->join('process_data_sources', 'processes.process_data_source_id', '=', 'process_data_sources.id')
            ->where('process_data_sources.slug', ProcessDataSourceSlug::JudicialBranch->value)
            ->whereNotNull('processes.process_id')
            ->where('processes.is_manual_sync', false);

        if ($radicadoFilter !== null && $radicadoFilter !== '') {
            $query->where('processes.process_number', $radicadoFilter);
        }

        return (int) $query->distinct()->count('processes.process_number');
    }

    private function countSamaiRadicados(?string $radicadoFilter): int
    {
        return (int) Process::query()
            ->forSamaiDailySync($radicadoFilter)
            ->count();
    }
}

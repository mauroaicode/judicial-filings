<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Services;

use Illuminate\Support\Facades\Artisan;
use Src\Application\Admin\JudicialSync\Data\AdminOrganizationJudicialSyncData;
use Src\Application\Admin\JudicialSync\Resources\AdminOrganizationJudicialSyncResource;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

readonly class AdminOrganizationJudicialSyncService
{
    public function handle(
        Organization $organization,
        AdminOrganizationJudicialSyncData $data,
    ): AdminOrganizationJudicialSyncResource {
        $this->ensureOrganizationCanTrack($organization);

        $exitCode = Artisan::call(
            'judicial:sync-processes-for-organization',
            $this->commandParameters($organization, $data),
        );

        abort_unless(
            $exitCode === ConsoleCommand::SUCCESS,
            500,
            __('process.sync_command_failed', ['source' => JudicialSyncDataSource::JudicialBranch->getLabel()])
        );

        return $this->resourceFor($organization, $data);
    }

    private function ensureOrganizationCanTrack(Organization $organization): void
    {
        abort_unless(
            $organization->canTrackProcesses(),
            422,
            __('process.organization_tracking_disabled')
        );
    }

    /**
     * @return array<string, string>
     */
    private function commandParameters(Organization $organization, AdminOrganizationJudicialSyncData $data): array
    {
        $parameters = ['organization' => $organization->id];
        $radicado = $this->radicadoFilter($data);

        if ($radicado === null) {
            return $parameters;
        }

        $parameters['--radicado'] = $radicado;

        return $parameters;
    }

    private function resourceFor(
        Organization $organization,
        AdminOrganizationJudicialSyncData $data,
    ): AdminOrganizationJudicialSyncResource {
        $radicado = $this->radicadoFilter($data);
        $jobsCount = $this->countRadicados($organization->id, $radicado);

        if ($jobsCount === 0) {
            return AdminOrganizationJudicialSyncResource::noJobs($organization, $radicado);
        }

        return AdminOrganizationJudicialSyncResource::fromDispatch($organization, $radicado, $jobsCount);
    }

    private function countRadicados(string $organizationId, ?string $radicadoFilter): int
    {
        return (int) Process::query()
            ->forJudicialDailySyncOfOrganization($organizationId, $radicadoFilter)
            ->count();
    }

    private function radicadoFilter(AdminOrganizationJudicialSyncData $data): ?string
    {
        if ($data->radicado === null || $data->radicado === '') {
            return null;
        }

        return $data->radicado;
    }
}

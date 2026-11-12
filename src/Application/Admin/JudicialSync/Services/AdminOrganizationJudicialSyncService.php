<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Services;

use Src\Application\Admin\JudicialSync\Data\AdminOrganizationJudicialSyncData;
use Src\Application\Admin\JudicialSync\Jobs\QueueOrganizationJudicialSyncJob;
use Src\Application\Admin\JudicialSync\Resources\AdminOrganizationJudicialSyncResource;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

readonly class AdminOrganizationJudicialSyncService
{
    public function handle(
        Organization $organization,
        AdminOrganizationJudicialSyncData $data,
    ): AdminOrganizationJudicialSyncResource {
        $this->ensureOrganizationCanTrack($organization);

        $radicado = $this->radicadoFilter($data);
        $jobsCount = $this->countRadicados($organization->id, $radicado);

        if ($jobsCount === 0) {
            return AdminOrganizationJudicialSyncResource::noJobs($organization, $radicado);
        }

        QueueOrganizationJudicialSyncJob::dispatch($organization->id, $radicado);

        return AdminOrganizationJudicialSyncResource::fromDispatch($organization, $radicado, $jobsCount);
    }

    private function ensureOrganizationCanTrack(Organization $organization): void
    {
        abort_unless(
            $organization->canTrackProcesses(),
            422,
            __('process.organization_tracking_disabled')
        );
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

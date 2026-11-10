<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\Organization\Models\Organization;

class AdminOrganizationJudicialSyncResource extends Resource
{
    public function __construct(
        public int $jobs_dispatched,
        public bool $batch_dispatched,
        public ?string $radicado_filter,
        public string $data_source,
        public string $data_source_label,
        public string $organization_id,
        public string $organization_name,
    ) {}

    public static function noJobs(Organization $organization, ?string $radicadoFilter): self
    {
        return self::make($organization, $radicadoFilter, 0, false);
    }

    public static function fromDispatch(Organization $organization, ?string $radicadoFilter, int $jobsCount): self
    {
        return self::make($organization, $radicadoFilter, $jobsCount, true);
    }

    private static function make(
        Organization $organization,
        ?string $radicadoFilter,
        int $jobsCount,
        bool $batchDispatched,
    ): self {
        return new self(
            jobs_dispatched: $jobsCount,
            batch_dispatched: $batchDispatched,
            radicado_filter: $radicadoFilter,
            data_source: JudicialSyncDataSource::JudicialBranch->value,
            data_source_label: JudicialSyncDataSource::JudicialBranch->getLabel(),
            organization_id: $organization->id,
            organization_name: $organization->name,
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;

class AdminJudicialSyncResource extends Resource
{
    public function __construct(
        public int $jobs_dispatched,
        public bool $batch_dispatched,
        public ?string $radicado_filter,
        public string $data_source,
        public string $data_source_label,
    ) {}

    public static function noJobs(?string $radicadoFilter, JudicialSyncDataSource $dataSource): self
    {
        return new self(
            jobs_dispatched: 0,
            batch_dispatched: false,
            radicado_filter: $radicadoFilter,
            data_source: $dataSource->value,
            data_source_label: $dataSource->getLabel(),
        );
    }

    public static function fromDispatch(int $jobsCount, ?string $radicadoFilter, JudicialSyncDataSource $dataSource): self
    {
        return new self(
            jobs_dispatched: $jobsCount,
            batch_dispatched: true,
            radicado_filter: $radicadoFilter,
            data_source: $dataSource->value,
            data_source_label: $dataSource->getLabel(),
        );
    }
}

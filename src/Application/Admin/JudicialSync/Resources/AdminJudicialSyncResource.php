<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Resources;

use Spatie\LaravelData\Resource;

class AdminJudicialSyncResource extends Resource
{
    public function __construct(
        public int $jobs_dispatched,
        public bool $batch_dispatched,
        public ?string $radicado_filter,
    ) {}

    public static function noJobs(?string $radicadoFilter): self
    {
        return new self(
            jobs_dispatched: 0,
            batch_dispatched: false,
            radicado_filter: $radicadoFilter,
        );
    }

    public static function fromDispatch(int $jobsCount, ?string $radicadoFilter): self
    {
        return new self(
            jobs_dispatched: $jobsCount,
            batch_dispatched: true,
            radicado_filter: $radicadoFilter,
        );
    }
}

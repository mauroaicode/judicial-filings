<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Resources;

use Spatie\LaravelData\Resource;

class OrganizationStatsResource extends Resource
{
    public function __construct(
        public int $total,
        public int $active,
        public int $inactive,
        public int $natural,
        public int $juridical,
    ) {}

    public static function fromCounts(
        int $total,
        int $active,
        int $inactive,
        int $natural,
        int $juridical,
    ): self {
        return new self(
            total: $total,
            active: $active,
            inactive: $inactive,
            natural: $natural,
            juridical: $juridical,
        );
    }
}

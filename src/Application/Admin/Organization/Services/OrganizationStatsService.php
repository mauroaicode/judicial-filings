<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Src\Application\Admin\Organization\Resources\OrganizationStatsResource;
use Src\Domain\Organization\Models\Organization;

readonly class OrganizationStatsService
{
    public function handle(): OrganizationStatsResource
    {
        return OrganizationStatsResource::fromCounts(
            total: $this->countTotal(),
            active: $this->countActive(),
            inactive: $this->countInactive(),
            natural: $this->countNatural(),
            juridical: $this->countJuridical(),
        );
    }

    private function countTotal(): int
    {
        return (int) Organization::query()->count();
    }

    private function countActive(): int
    {
        return (int) Organization::query()->whereActive()->count();
    }

    private function countInactive(): int
    {
        return (int) Organization::query()->whereInactive()->count();
    }

    private function countNatural(): int
    {
        return (int) Organization::query()->whereNatural()->count();
    }

    private function countJuridical(): int
    {
        return (int) Organization::query()->whereJuridical()->count();
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Services;

use Src\Application\AppUser\Organization\Data\OrganizationProcessQuotaData;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Models\Organization;

readonly class GetOrganizationProcessQuotaService
{
    public function __construct(
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

    public function handle(Organization $organization): OrganizationProcessQuotaData
    {
        return OrganizationProcessQuotaData::fromSummary(
            $this->organizationProcessQuotaService->getSummary($organization->id),
        );
    }
}

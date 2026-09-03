<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Src\Application\Admin\Organization\Resources\OrganizationDetailResource;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Models\Organization;

readonly class OrganizationDetailService
{
    public function __construct(
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

    public function handle(Organization $organization): OrganizationDetailResource
    {
        $this->organizationProcessQuotaService->ensureSettings($organization);

        return OrganizationDetailResource::fromModel(
            $organization->fresh(['settings']) ?? $organization,
            $this->organizationProcessQuotaService,
        );
    }
}

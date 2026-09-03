<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Controllers;

use Src\Application\AppUser\Organization\Data\OrganizationProcessQuotaData;
use Src\Application\AppUser\Organization\Services\GetOrganizationProcessQuotaService;
use Src\Application\AppUser\Organization\Services\ResolveUserOrganizationService;
use Src\Domain\Organization\Models\Organization;

class GetOrganizationProcessQuotaController
{
    public function __construct(
        private readonly ResolveUserOrganizationService $resolveUserOrganizationService,
        private readonly GetOrganizationProcessQuotaService $getOrganizationProcessQuotaService,
    ) {}

    public function __invoke(): OrganizationProcessQuotaData
    {
        $organization = $this->resolveUserOrganizationService->handle();

        if (! $organization instanceof Organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        return $this->getOrganizationProcessQuotaService->handle($organization);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Controllers;

use Src\Application\AppUser\Organization\Data\OrganizationAiStatusResource;
use Src\Application\AppUser\Organization\Services\GetOrganizationAiStatusService;

class GetOrganizationAiStatusController
{
    public function __construct(
        private readonly GetOrganizationAiStatusService $service
    ) {}

    public function __invoke(string $organizationId): OrganizationAiStatusResource
    {
        $isAiEnabled = $this->service->handle($organizationId);

        return new OrganizationAiStatusResource($isAiEnabled);
    }
}

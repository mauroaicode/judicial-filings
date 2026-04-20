<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Services;

use Src\Domain\Organization\Models\Organization;

class GetOrganizationAiStatusService
{
    public function handle(string $organizationId): bool
    {
        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($organizationId);

        return $organization->is_ai_enabled;
    }
}

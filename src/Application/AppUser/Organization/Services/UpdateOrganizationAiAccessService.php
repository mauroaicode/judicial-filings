<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Services;

use Src\Application\AppUser\Organization\Data\UpdateOrganizationAiAccessData;
use Src\Domain\Organization\Models\Organization;

class UpdateOrganizationAiAccessService
{
    public function handle(string $organizationId, UpdateOrganizationAiAccessData $data): void
    {
        $this->updateAiAccess($organizationId, $data->is_ai_enabled);
    }

    private function updateAiAccess(string $organizationId, bool $isAiEnabled): void
    {
        Organization::query()
            ->where('id', $organizationId)
            ->update([
                'is_ai_enabled' => $isAiEnabled,
            ]);
    }
}

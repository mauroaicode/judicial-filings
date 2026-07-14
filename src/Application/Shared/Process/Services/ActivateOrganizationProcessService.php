<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Validation\ValidationException;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;

readonly class ActivateOrganizationProcessService
{
    /**
     * Mark the organization–process relationship as active again.
     */
    public function handle(string $organizationId, string $processId): void
    {
        $updated = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->update([
                'status' => OrganizationProcessStatus::ACTIVE->value,
                'is_active' => true,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'process_id' => [__('process.relationship_not_found')],
            ]);
        }
    }
}

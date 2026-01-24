<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Src\Domain\Process\Models\Process;

readonly class ProcessDetailService
{
    /**
     * Get process detail with subjects for an organization.
     *
     * @param  string  $processId  The process ID.
     * @param  string  $organizationId  The organization ID.
     */
    public function handle(string $processId, string $organizationId): ?Process
    {
        return Process::query()
            ->where('id', $processId)
            ->whereOrganization($organizationId)
            ->withSubjects()
            ->with(['organizations' => function ($query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            }])
            ->first();
    }
}

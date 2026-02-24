<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Src\Domain\Process\Models\Process;

readonly class ToggleProcessStatusService
{
    /**
     * Toggle the active status of a process for the given organization.
     *
     * @param  string  $processId  The process UUID.
     * @param  string  $organizationId  The organization UUID.
     * @param  bool  $isActive  The desired active status.
     */
    public function handle(string $processId, string $organizationId, bool $isActive): void
    {
        $process = $this->findProcessForOrganization($processId, $organizationId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $this->updatePivotStatus($process, $organizationId, $isActive);
    }

    /**
     * Find a process by ID that belongs to the given organization.
     */
    private function findProcessForOrganization(string $processId, string $organizationId): ?Process
    {
        return Process::query()
            ->where('id', $processId)
            ->whereOrganization($organizationId)
            ->first();
    }

    /**
     * Update the is_active flag on the organization_processes pivot.
     */
    private function updatePivotStatus(Process $process, string $organizationId, bool $isActive): void
    {
        $process->organizations()->updateExistingPivot($organizationId, [
            'is_active' => $isActive,
        ]);
    }
}

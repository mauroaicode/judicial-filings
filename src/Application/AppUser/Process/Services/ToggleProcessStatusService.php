<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\Process;

readonly class ToggleProcessStatusService
{
    public function __construct(
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

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

        $this->ensureNotSuspendedByAgenda($organizationId, $processId);

        if ($isActive) {
            $this->organizationProcessQuotaService->assertCanActivateProcess($organizationId, $processId);
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

    private function ensureNotSuspendedByAgenda(string $organizationId, string $processId): void
    {
        $organizationProcess = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->first();

        if (
            $organizationProcess
            && OrganizationProcessStatus::fromPivot($organizationProcess) === OrganizationProcessStatus::SUSPENDED
        ) {
            throw ValidationException::withMessages([
                'is_active' => [__('process.cannot_toggle_while_suspended')],
            ]);
        }
    }

    /**
     * Update the is_active flag and status on the organization_processes pivot.
     */
    private function updatePivotStatus(Process $process, string $organizationId, bool $isActive): void
    {
        $status = OrganizationProcessStatus::fromBoolean($isActive);

        $process->organizations()->updateExistingPivot($organizationId, [
            'is_active' => $isActive,
            'status' => $status->value,
        ]);
    }
}

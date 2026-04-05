<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Process\Data\UpdateProcessConfigData;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Shared\Enums\SeverityColor;

readonly class UpdateProcessConfigService
{
    /**
     * Update the configuration (lawyer_role, alert_level) for a given process/organization relationship.
     *
     * @param  string  $organizationId  The ID of the organization.
     * @param  string  $processId  The ID of the process.
     * @param  UpdateProcessConfigData  $data  The data containing the new configuration.
     */
    public function handle(string $organizationId, string $processId, UpdateProcessConfigData $data): void
    {
        $organizationProcess = $this->findRelationship($organizationId, $processId);

        $alertLevel = $this->calculateAlertLevel($organizationProcess, $data->lawyer_role);

        DB::table('organization_processes')
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->update([
                'lawyer_role' => $data->lawyer_role?->value,
                'inactivity_alert_level' => $alertLevel,
                'updated_at' => now(),
            ]);
    }

    /**
     * Find the relationship between organization and process.
     */
    private function findRelationship(string $organizationId, string $processId): OrganizationProcess
    {
        $organizationProcess = OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->first();

        if (! $organizationProcess) {
            abort(404, __('process.relationship_not_found'));
        }

        return $organizationProcess;
    }

    /**
     * Calculate the alert level based on the lawyer role and process inactivity.
     */
    private function calculateAlertLevel(OrganizationProcess $organizationProcess, ?ProcessLawyerRole $role): ?string
    {
        if (is_null($role)) {
            return null;
        }

        $process = $organizationProcess->process;

        if (! $process->last_activity_date) {
            return null;
        }

        $daysInactive = (int) Date::parse($process->last_activity_date)->diffInDays(now());

        return match ($role) {
            ProcessLawyerRole::PLAINTIFF => $this->getPlaintiffAlertLevel($daysInactive),
            ProcessLawyerRole::DEFENDANT => $this->getDefendantAlertLevel($daysInactive),
        };
    }

    private function getPlaintiffAlertLevel(int $days): ?string
    {
        if ($days >= config('semaphores.inactivity_thresholds.'.ProcessLawyerRole::PLAINTIFF->value.'.'.SeverityColor::RED->value, 90)) {
            return SeverityColor::RED->value;
        }

        if ($days >= config('semaphores.inactivity_thresholds.'.ProcessLawyerRole::PLAINTIFF->value.'.'.SeverityColor::YELLOW->value, 45)) {
            return SeverityColor::YELLOW->value;
        }

        return null;
    }

    private function getDefendantAlertLevel(int $days): ?string
    {
        if ($days >= config('semaphores.inactivity_thresholds.'.ProcessLawyerRole::DEFENDANT->value.'.'.SeverityColor::GREEN->value, 90)) {
            return SeverityColor::GREEN->value;
        }

        return null;
    }
}

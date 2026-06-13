<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Process\Data\UpdateProcessConfigData;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessLawyerRole;

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

        return ProcessAlertLevelHelper::calculate(
            Date::parse($process->last_activity_date),
            $role,
        );
    }
}

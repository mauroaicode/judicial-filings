<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Process\Data\BulkUpdateProcessConfigData;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Shared\Enums\SeverityColor;

readonly class BulkUpdateProcessConfigService
{
    /**
     * Handle bulk update of processes configuration.
     *
     * @return array<string, mixed>
     */
    public function handle(string $organizationId, BulkUpdateProcessConfigData $data): array
    {
        // 1. Get the radicados (process_numbers) for the provided process IDs
        $processNumbers = Process::query()
            ->whereIn('id', $data->process_ids)
            ->pluck('process_number')
            ->unique()
            ->all();

        // 2. Find ALL instances of those radicados for this organization
        $organizationProcesses = OrganizationProcess::query()
            ->with('process')
            ->where('organization_id', $organizationId)
            ->whereHas('process', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($processNumbers): void {
                $query->whereIn('process_number', $processNumbers);
            })
            ->get();

        $results = [
            'red' => collect(),
            'yellow' => collect(),
            'green' => collect(),
            'none' => collect(),
            'failed' => collect(),
        ];

        foreach ($organizationProcesses as $orgProcess) {
            try {
                $alertLevel = $this->calculateAndSave($organizationId, $orgProcess, $data->lawyer_role);

                $key = $alertLevel ?? 'none';
                $results[$key]->push($orgProcess->process_id);
            } catch (\Throwable) {
                $results['failed']->push($orgProcess->process->process_number);
            }
        }

        return $this->formatSummary($results);
    }

    /**
     * Calculate alert level and persist it.
     */
    private function calculateAndSave(string $organizationId, OrganizationProcess $orgProcess, ProcessLawyerRole $role): ?string
    {
        $alertLevel = $this->calculateAlertLevel($orgProcess, $role);

        DB::table('organization_processes')
            ->where('organization_id', $organizationId)
            ->where('process_id', $orgProcess->process_id)
            ->update([
                'lawyer_role' => $role->value,
                'inactivity_alert_level' => $alertLevel,
                'updated_at' => now(),
            ]);

        return $alertLevel;
    }

    /**
     * Business logic for alert level calculation.
     */
    private function calculateAlertLevel(OrganizationProcess $orgProcess, ProcessLawyerRole $role): ?string
    {
        $process = $orgProcess->process;
        if (! $process->last_activity_date) {
            return null;
        }

        $days = (int) Date::parse($process->last_activity_date)->diffInDays(now());

        return match ($role) {
            ProcessLawyerRole::PLAINTIFF => $this->getPlaintiffLevel($days),
            ProcessLawyerRole::DEFENDANT => $this->getDefendantLevel($days),
        };
    }

    private function getPlaintiffLevel(int $days): string
    {
        if ($days >= (int) config('semaphores.inactivity_thresholds.'.ProcessLawyerRole::PLAINTIFF->value.'.'.SeverityColor::RED->value, 90)) {
            return SeverityColor::RED->value;
        }

        if ($days >= (int) config('semaphores.inactivity_thresholds.'.ProcessLawyerRole::PLAINTIFF->value.'.'.SeverityColor::YELLOW->value, 45)) {
            return SeverityColor::YELLOW->value;
        }

        return SeverityColor::GREEN->value;
    }

    private function getDefendantLevel(int $days): ?string
    {
        if ($days >= (int) config('semaphores.inactivity_thresholds.'.ProcessLawyerRole::DEFENDANT->value.'.'.SeverityColor::GREEN->value, 90)) {
            return SeverityColor::GREEN->value;
        }

        return null;
    }

    /**
     * Format the final summary report.
     *
     * @param  array<string, Collection<int, string>>  $results
     * @return array<string, mixed>
     */
    private function formatSummary(array $results): array
    {
        $total = $results['red']->count() + $results['yellow']->count() + $results['green']->count() + $results['none']->count();

        return [
            'message' => __(':count processes have been updated.', ['count' => $total]),
            'total_updated' => $total,
            'red_alerts' => [
                'count' => $results['red']->count(),
                'process_ids' => $results['red']->all(),
            ],
            'yellow_alerts' => [
                'count' => $results['yellow']->count(),
                'process_ids' => $results['yellow']->all(),
            ],
            'green_alerts' => [
                'count' => $results['green']->count(),
                'process_ids' => $results['green']->all(),
            ],
            'no_alerts' => [
                'count' => $results['none']->count(),
                'process_ids' => $results['none']->all(),
            ],
            'failed' => [
                'count' => $results['failed']->count(),
                'process_numbers' => $results['failed']->all(),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Dashboard\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Src\Application\Admin\Dashboard\Resources\AdminDashboardStatsResource;
use Src\Domain\Process\Models\Process;

readonly class AdminDashboardStatsService
{
    private const OUTDATED_HOURS = 48;

    public function handle(): AdminDashboardStatsResource
    {
        $processCounts = $this->getProcessCounts();
        $outdatedProcesses = $this->getOutdatedProcessesCount();
        $criticalAlertProcesses = $this->getCriticalAlertProcessesCount();
        $earlyAttentionProcesses = $this->getEarlyAttentionProcessesCount();

        return AdminDashboardStatsResource::fromCounts(
            totalProcesses: $processCounts['total'],
            activeProcesses: $processCounts['active'],
            orphanProcesses: $processCounts['orphans'],
            processesWithMultipleInstances: $processCounts['multiple_instances'],
            outdatedProcesses: $outdatedProcesses,
            criticalAlertProcesses: $criticalAlertProcesses,
            earlyAttentionProcesses: $earlyAttentionProcesses,
        );
    }

    /**
     * Admin KPIs sobre radicado único (process_number); activo/huérfano solo tabla processes.status.
     *
     * @return array{total: int, active: int, orphans: int, multiple_instances: int}
     */
    private function getProcessCounts(): array
    {
        $total = (int) Process::query()
            ->distinct()
            ->count('process_number');

        $active = (int) Process::query()
            ->whereJudiciallyActive()
            ->distinct()
            ->count('process_number');

        $orphans = (int) Process::query()
            ->whereJudiciallyInactive()
            ->distinct()
            ->count('process_number');

        $multipleInstances = (int) Process::query()
            ->whereHas('organizations')
            ->where('has_multiple_instances', true)
            ->distinct()
            ->count('process_number');

        return [
            'total' => $total,
            'active' => $active,
            'orphans' => $orphans,
            'multiple_instances' => $multipleInstances,
        ];
    }

    /**
     * Counts processes that have not been synced with the Rama Judicial in the last 48 hours.
     * A process is considered outdated if last_api_update is null or older than 48 hours.
     * This indicates the scraper/proxy may be failing for those processes.
     */
    private function getOutdatedProcessesCount(): int
    {
        $threshold = now()->subHours(self::OUTDATED_HOURS);

        return (int) Process::query()
            ->whereHas('organizations')
            ->where(function (Builder $query) use ($threshold): void {
                $query->whereNull('last_api_update')
                    ->orWhere('last_api_update', '<', $threshold);
            })
            ->distinct()
            ->count('process_number');
    }

    /**
     * Counts processes globally flagged as critical (semáforo rojo).
     * A process is critical when it has at least one organization link with
     * inactivity_alert_level = 'red', meaning it has been inactive for >= 90 days
     * where the organization acts as plaintiff (demandante).
     */
    private function getCriticalAlertProcessesCount(): int
    {
        return (int) Process::query()
            ->whereHas('organizations', function (Builder $query): void {
                $query->where('organization_processes.inactivity_alert_level', 'red');
            })
            ->distinct()
            ->count('process_number');
    }

    /**
     * Counts processes with early attention alerts (semáforo amarillo).
     */
    private function getEarlyAttentionProcessesCount(): int
    {
        return (int) Process::query()
            ->whereHas('organizations', function (Builder $query): void {
                $query->where('organization_processes.inactivity_alert_level', 'yellow');
            })
            ->distinct()
            ->count('process_number');
    }
}

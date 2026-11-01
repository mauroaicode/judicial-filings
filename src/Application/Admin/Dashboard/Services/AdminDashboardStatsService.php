<?php

declare(strict_types=1);

namespace Src\Application\Admin\Dashboard\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Src\Application\Admin\Dashboard\Resources\AdminDashboardStatsResource;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\QueryBuilders\ProcessQueryBuilder;

readonly class AdminDashboardStatsService
{
    private const OUTDATED_HOURS = 48;

    public function handle(ProcessFilterData $filters): AdminDashboardStatsResource
    {
        $processCounts = $this->getProcessCounts($filters);
        $outdatedProcesses = $this->getOutdatedProcessesCount($filters);
        $criticalAlertProcesses = $this->getCriticalAlertProcessesCount($filters);
        $earlyAttentionProcesses = $this->getEarlyAttentionProcessesCount($filters);

        $pendingManualRegistrations = $this->countPendingManualRegistrations($filters);

        return AdminDashboardStatsResource::fromCounts(
            totalProcesses: $processCounts['total'],
            activeProcesses: $processCounts['active'],
            orphanProcesses: $processCounts['orphans'],
            privateProcesses: $processCounts['private_processes'],
            processesWithMultipleInstances: $processCounts['multiple_instances'],
            outdatedProcesses: $outdatedProcesses,
            criticalAlertProcesses: $criticalAlertProcesses,
            earlyAttentionProcesses: $earlyAttentionProcesses,
            pendingManualRegistrations: $pendingManualRegistrations,
        );
    }

    /**
     * Same filter stack as {@see \Src\Application\Admin\Process\Controllers\ProcessController::index}.
     */
    private function filteredProcessesQuery(ProcessFilterData $filters): ProcessQueryBuilder
    {
        return Process::query()->filters($filters);
    }

    private function countDistinctRadicados(ProcessQueryBuilder $query): int
    {
        return (int) (clone $query)->distinct()->count('process_number');
    }

    /**
     * @return array{total: int, active: int, orphans: int, private_processes: int, multiple_instances: int}
     */
    private function getProcessCounts(ProcessFilterData $filters): array
    {
        $base = $this->filteredProcessesQuery($filters);

        $total = $this->countDistinctRadicados($base);

        $active = $this->countDistinctRadicados(
            (clone $base)->whereJudiciallyActive(),
        );

        $orphans = $this->countDistinctRadicados(
            (clone $base)->whereJudiciallyInactive(),
        );

        $privateProcesses = $this->countDistinctRadicados(
            (clone $base)->where('is_private', true),
        );

        $multipleInstances = $this->countDistinctRadicados(
            (clone $base)
                ->whereHas('organizations')
                ->where('has_multiple_instances', true),
        );

        return [
            'total' => $total,
            'active' => $active,
            'orphans' => $orphans,
            'private_processes' => $privateProcesses,
            'multiple_instances' => $multipleInstances,
        ];
    }

    private function getOutdatedProcessesCount(ProcessFilterData $filters): int
    {
        $threshold = now()->subHours(self::OUTDATED_HOURS);

        return $this->countDistinctRadicados(
            $this->filteredProcessesQuery($filters)
                ->whereHas('organizations')
                ->where(function (Builder $query) use ($threshold): void {
                    $query->whereNull('last_api_update')
                        ->orWhere('last_api_update', '<', $threshold);
                }),
        );
    }

    private function getCriticalAlertProcessesCount(ProcessFilterData $filters): int
    {
        return $this->countDistinctRadicados(
            $this->filteredProcessesQuery($filters)
                ->whereHas('organizations', function (Builder $query): void {
                    $query->where('organization_processes.inactivity_alert_level', 'red');
                }),
        );
    }

    private function getEarlyAttentionProcessesCount(ProcessFilterData $filters): int
    {
        return $this->countDistinctRadicados(
            $this->filteredProcessesQuery($filters)
                ->whereHas('organizations', function (Builder $query): void {
                    $query->where('organization_processes.inactivity_alert_level', 'yellow');
                }),
        );
    }

    private function countPendingManualRegistrations(ProcessFilterData $filters): int
    {
        $query = ManualRegistrationRequest::query()
            ->where('status', ManualRegistrationRequestStatus::Pending->value);

        if ($filters->organization_id !== null && trim($filters->organization_id) !== '') {
            $query->where('organization_id', trim($filters->organization_id));
        }

        return (int) $query->count();
    }
}

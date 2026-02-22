<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Dashboard\Resources\DashboardStatsResource;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\Process;

readonly class DashboardStatsService
{
    public function handle(string $organizationId, ProcessFilterData $filters): DashboardStatsResource
    {
        $processCounts = $this->getProcessCounts($organizationId, $filters);
        $notificationCountsByType = $this->getNotificationCountsByType($organizationId);

        return DashboardStatsResource::fromCounts(
            totalProcesses: $processCounts['total'],
            activeProcesses: $processCounts['active'],
            inactiveProcesses: $processCounts['inactive'],
            processesWithMultipleInstances: $processCounts['multiple_instances'],
            notificationsByType: $notificationCountsByType
        );
    }

    /**
     * Counts unique radicados (by process_number) applying the same filters as the process list.
     * Uses the filteredIdsQuery pattern to ensure consistent results with the listing endpoint.
     *
     * @return array{total: int, active: int, inactive: int, multiple_instances: int}
     */
    private function getProcessCounts(string $organizationId, ProcessFilterData $filters): array
    {
        $filteredIdsQuery = Process::query()
            ->whereOrganization($organizationId)
            ->filters($filters)
            ->select('id');

        $total = (int) Process::query()
            ->whereIn('id', $filteredIdsQuery)
            ->distinct()
            ->count('process_number');

        $active = (int) Process::query()
            ->whereIn('id', $filteredIdsQuery)
            ->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($organizationId): void {
                $q->where('organizations.id', $organizationId)
                    ->where('organization_processes.is_active', true);
            })
            ->distinct()
            ->count('process_number');

        $multipleInstances = (int) DB::table(
            Process::query()
                ->whereIn('id', $filteredIdsQuery)
                ->select('process_number')
                ->groupBy('process_number')
                ->havingRaw('COUNT(*) > 1')
                ->toBase(),
            'grouped'
        )->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'multiple_instances' => $multipleInstances,
        ];
    }

    /**
     * @return array{actuacion: int, actuacion_alerta: int}
     */
    private function getNotificationCountsByType(string $organizationId): array
    {
        $counts = OrganizationNotification::query()
            ->whereOrganization($organizationId)
            ->whereUnviewed()
            ->selectRaw('notification_type, count(*) as count')
            ->groupBy('notification_type')
            ->pluck('count', 'notification_type')
            ->all();

        return [
            'actuacion' => (int) ($counts['actuacion'] ?? 0),
            'actuacion_alerta' => (int) ($counts['actuacion_alerta'] ?? 0),
        ];
    }
}

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
        if ($filters->lawyer_role === 'none' && $filters->severity_color === 'none') {
            abort(422, __('process.invalid_none_combination'));
        }

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
     * Uses the filtered base query pattern to ensure consistent results with the listing endpoint.
     *
     * @return array{total: int, active: int, inactive: int, multiple_instances: int}
     */
    private function getProcessCounts(string $organizationId, ProcessFilterData $filters): array
    {
        $baseQueryBuilder = function (ProcessFilterData $currentFilters) use ($organizationId) {
            $nonPivotFilters = clone $currentFilters;
            $nonPivotFilters->lawyer_role = null;
            $nonPivotFilters->severity_color = null;
            $nonPivotFilters->status = null;

            return Process::query()
                ->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($organizationId, $currentFilters): void {
                    $query->where('organizations.id', $organizationId);

                    if ($currentFilters->status) {
                        $isActive = \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::tryFrom($currentFilters->status) === \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE;
                        $query->where('organization_processes.is_active', $isActive);
                    }

                    if ($currentFilters->lawyer_role) {
                        if ($currentFilters->lawyer_role === 'none') {
                            $query->whereNull('organization_processes.lawyer_role');
                        } else {
                            $query->where('organization_processes.lawyer_role', $currentFilters->lawyer_role);
                        }
                    }

                    if ($currentFilters->severity_color) {
                        if ($currentFilters->severity_color === 'none') {
                            $query->where(function (\Illuminate\Contracts\Database\Query\Builder $q): void {
                                $q->whereNull('organization_processes.inactivity_alert_level')
                                    ->orWhereHas('process', fn (\Illuminate\Contracts\Database\Query\Builder $p) => $p->whereNull('last_activity_date'));
                            });
                        } else {
                            $query->where('organization_processes.inactivity_alert_level', $currentFilters->severity_color);
                        }
                    }
                })
                ->filters($nonPivotFilters);
        };

        $totalQuery = $baseQueryBuilder($filters);
        $total = (int) $totalQuery->distinct()->count('process_number');

        $activeFilters = clone $filters;
        $activeFilters->status = \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE->value;

        $active = (int) $baseQueryBuilder($activeFilters)->distinct()->count('process_number');

        $multipleInstances = (int) DB::table(
            $baseQueryBuilder($filters)
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

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Dashboard\Resources\DashboardStatsResource;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
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
                ->whereHas('organizations', function (Builder $query) use ($organizationId, $currentFilters): void {
                    $query->where('organizations.id', $organizationId);

                    if ($currentFilters->status) {
                        $isActive = OrganizationProcessStatus::tryFrom($currentFilters->status) === OrganizationProcessStatus::ACTIVE;
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
                            $movingDays = (int) config('semaphores.moving_days_green', 30);
                            $cutoff = now()->subDays($movingDays)->startOfDay()->toDateString();

                            $query->where(function (Builder $q): void {
                                $q->whereNull('organization_processes.inactivity_alert_level')
                                    ->orWhereExists(function (Builder $sub): void {
                                        $sub->selectRaw('1')
                                            ->from('processes')
                                            ->whereColumn('processes.id', 'organization_processes.process_id')
                                            ->whereNull('processes.last_activity_date');
                                    });
                            });

                            // Same semantics as the listing endpoint: exclude plaintiff processes
                            // that would be shown as green due to recent activity (<= movingDays).
                            $query->where(function (Builder $q) use ($cutoff): void {
                                $q->whereNull('organization_processes.lawyer_role')
                                    ->orWhere('organization_processes.lawyer_role', '!=', 'plaintiff')
                                    ->orWhereExists(function (Builder $sub) use ($cutoff): void {
                                        $sub->selectRaw('1')
                                            ->from('processes')
                                            ->whereColumn('processes.id', 'organization_processes.process_id')
                                            ->where(function (Builder $p) use ($cutoff): void {
                                                $p->whereNull('processes.last_activity_date')
                                                    ->orWhere('processes.last_activity_date', '<=', $cutoff);
                                            });
                                    });
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
        $activeFilters->status = OrganizationProcessStatus::ACTIVE->value;

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

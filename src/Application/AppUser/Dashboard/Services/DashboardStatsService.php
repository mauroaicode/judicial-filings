<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Dashboard\Resources\DashboardStatsResource;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Application\Shared\Helpers\ProcessRepresentativeSeverityFilter;
use Src\Application\Shared\Services\Notification\OrganizationNotificationRegistrationCutoffService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Models\Process;
use Src\Domain\Shared\Enums\SeverityColor;

readonly class DashboardStatsService
{
    public function __construct(
        private OrganizationNotificationRegistrationCutoffService $registrationCutoffService,
    ) {}

    public function handle(string $organizationId, ProcessFilterData $filters): DashboardStatsResource
    {
        if ($filters->lawyer_role === 'none' && $filters->severity_color === 'none') {
            abort(422, __('process.invalid_none_combination'));
        }

        $processCounts = $this->getProcessCounts($organizationId, $filters);
        $notificationCountsByType = $this->getNotificationCountsByType($organizationId);
        $semaphoreCounts = $this->getSemaphoreCounts($organizationId, $filters);

        return DashboardStatsResource::fromCounts(
            totalProcesses: $processCounts['total'],
            activeProcesses: $processCounts['active'],
            inactiveProcesses: $processCounts['inactive'],
            processesWithMultipleInstances: $processCounts['multiple_instances'],
            notificationsByType: $notificationCountsByType,
            semaphoreCounts: $semaphoreCounts,
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
        $baseQueryBuilder = fn (ProcessFilterData $currentFilters): \Illuminate\Database\Eloquent\Builder => $this->buildFilteredProcessQuery(
            $organizationId,
            $currentFilters,
        );

        $total = $this->countDistinctRadicados($baseQueryBuilder($filters));

        $activeFilters = clone $filters;
        $activeFilters->status = OrganizationProcessStatus::ACTIVE->value;

        $active = $this->countDistinctRadicados($baseQueryBuilder($activeFilters));

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
     * Counts one semaphore color per radicado, using the same representative instance
     * as the process list (latest last_activity_date among filtered instances).
     *
     * @return array{red: int, yellow: int, green: int}
     */
    private function getSemaphoreCounts(string $organizationId, ProcessFilterData $filters): array
    {
        $baseFilters = clone $filters;
        $baseFilters->severity_color = null;

        $processes = $this->buildFilteredProcessQuery($organizationId, $baseFilters)
            ->select(['processes.id', 'processes.process_number', 'processes.last_activity_date'])
            ->with(['organizations' => function ($query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            }])
            ->get();

        $counts = [
            SeverityColor::RED->value => 0,
            SeverityColor::YELLOW->value => 0,
            SeverityColor::GREEN->value => 0,
        ];

        foreach ($processes->groupBy('process_number') as $instances) {
            $color = ProcessRepresentativeSeverityFilter::resolveRepresentativeColor($instances, $organizationId);

            if ($color !== null && array_key_exists($color, $counts)) {
                $counts[$color]++;
            }
        }

        return $counts;
    }

    private function countDistinctRadicados(\Illuminate\Database\Eloquent\Builder $query): int
    {
        return (int) $query->distinct()->count('process_number');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Process>
     */
    private function buildFilteredProcessQuery(string $organizationId, ProcessFilterData $filters): \Illuminate\Database\Eloquent\Builder
    {
        $nonPivotFilters = clone $filters;
        $nonPivotFilters->lawyer_role = null;
        $nonPivotFilters->severity_color = null;
        $nonPivotFilters->status = null;

        $query = Process::query()
            ->whereHas('organizations', function (Builder $query) use ($organizationId, $filters): void {
                $query->where('organizations.id', $organizationId);

                if ($filters->status) {
                    $isActive = OrganizationProcessStatus::tryFrom($filters->status) === OrganizationProcessStatus::ACTIVE;
                    $query->where('organization_processes.is_active', $isActive);
                }

                if ($filters->lawyer_role) {
                    if ($filters->lawyer_role === 'none') {
                        $query->whereNull('organization_processes.lawyer_role');
                    } else {
                        $query->where('organization_processes.lawyer_role', $filters->lawyer_role);
                    }
                }
            })
            ->filters($nonPivotFilters);

        if ($filters->severity_color) {
            ProcessRepresentativeSeverityFilter::apply($query, $organizationId, $filters->severity_color);
        }

        return $query;
    }

    /**
     * @return array{actuacion: int, actuacion_alerta: int}
     */
    private function getNotificationCountsByType(string $organizationId): array
    {
        $baseQuery = OrganizationNotification::query()
            ->whereOrganization($organizationId)
            ->whereUnviewed();

        $this->registrationCutoffService->applyBellDisplayFilter($baseQuery, $organizationId);

        $counts = $baseQuery
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

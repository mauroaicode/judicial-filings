<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Src\Application\AppUser\Process\Resources\ProcessIndexResource;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\Process\Models\Process;

readonly class ProcessFinderService
{
    /**
     * Get paginated processes grouped by radicado (process_number): one row per radicado,
     * with the representative instance being the one with the latest last_activity_date. Each item
     * includes an `instances` array with all instances (same shape as the row) for the
     * frontend dropdown, ordered by last_activity_date DESC.
     *
     * @param  ProcessFilterData  $filters  The filtering criteria.
     * @param  string  $organizationId  The organization ID.
     * @param  int  $perPage  Number of radicados per page.
     * @param  int  $page  Current page (1-based).
     */
    public function handle(ProcessFilterData $filters, string $organizationId, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        if ($filters->lawyer_role === 'none' && $filters->severity_color === 'none') {
            abort(422, __('process.invalid_none_combination'));
        }

        // 0. Prepare a version of filters without pivot-based ones to avoid conflicts with filters()
        $nonPivotFilters = clone $filters;
        $nonPivotFilters->lawyer_role = null;
        $nonPivotFilters->severity_color = null;
        $nonPivotFilters->status = null;

        $baseQuery = (fn () => Process::query()
            // Strict organization-aware pivot filtering
            ->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($organizationId, $filters): void {
                $query->where('organizations.id', $organizationId);

                if ($filters->status) {
                    $isActive = \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::tryFrom($filters->status) === \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE;
                    $query->where('organization_processes.is_active', $isActive);
                }

                if ($filters->lawyer_role) {
                    if ($filters->lawyer_role === 'none') {
                        $query->whereNull('organization_processes.lawyer_role');
                    } else {
                        $query->where('organization_processes.lawyer_role', $filters->lawyer_role);
                    }
                }

                if ($filters->severity_color) {
                    if ($filters->severity_color === 'none') {
                        $query->where(function (\Illuminate\Contracts\Database\Query\Builder $q): void {
                            $q->whereNull('organization_processes.inactivity_alert_level')
                                ->orWhereHas('process', fn (\Illuminate\Contracts\Database\Query\Builder $p) => $p->whereNull('last_activity_date'));
                        });
                    } else {
                        $query->where('organization_processes.inactivity_alert_level', $filters->severity_color);
                    }
                }
            })
            // Apply all OTHER non-pivot filters (court, dates, etc.)
            ->filters($nonPivotFilters));

        $total = $baseQuery()
            ->selectRaw('COUNT(DISTINCT process_number) as total')
            ->value('total') ?? 0;

        $processNumbers = $baseQuery()
            ->selectRaw('process_number, MAX(last_activity_date) as max_activity_date')
            ->groupBy('process_number')
            ->latest('max_activity_date')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->pluck('process_number')
            ->values()
            ->all();

        if ($processNumbers === []) {
            return new LengthAwarePaginatorImpl(
                collect(),
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        $processes = Process::query()
            ->whereIn('process_number', $processNumbers)
            // Apply SAME filters here for consistency in instances
            ->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($organizationId, $filters): void {
                $query->where('organizations.id', $organizationId);
                // ... Re-apply pivot logic exactly as in baseQuery for strict results
                if ($filters->status) {
                    $isActive = \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::tryFrom($filters->status) === \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE;
                    $query->where('organization_processes.is_active', $isActive);
                }

                if ($filters->lawyer_role) {
                    if ($filters->lawyer_role === 'none') {
                        $query->whereNull('organization_processes.lawyer_role');
                    } else {
                        $query->where('organization_processes.lawyer_role', $filters->lawyer_role);
                    }
                }

                if ($filters->severity_color) {
                    if ($filters->severity_color === 'none') {
                        $query->where(function (\Illuminate\Contracts\Database\Query\Builder $q): void {
                            $q->whereNull('organization_processes.inactivity_alert_level')
                                ->orWhereHas('process', fn (\Illuminate\Contracts\Database\Query\Builder $p) => $p->whereNull('last_activity_date'));
                        });
                    } else {
                        $query->where('organization_processes.inactivity_alert_level', $filters->severity_color);
                    }
                }
            })
            ->filters($nonPivotFilters)
            ->with(['subjects', 'organizations' => function ($query) use ($organizationId): void {
                // IMPORTANT: Only filter by ID here, don't re-filter by color/role
                // so the resource always has the pivot data to show.
                $query->where('organizations.id', $organizationId);
            }])
            ->orderedByLastActivityDate()
            ->get();

        $byNumber = $processes->groupBy('process_number');
        $startIndex = (($page - 1) * $perPage) + 1;
        $items = collect();

        foreach ($processNumbers as $position => $processNumber) {
            $instances = $byNumber->get($processNumber, collect())->values();

            // The instances are already filtered by the query above.
            // Just take the first one as representative.
            $representative = $instances->first();

            if (! $representative instanceof Process) {
                continue;
            }

            $index = $startIndex + $position;
            $row = ProcessIndexResource::fromModel($representative, $organizationId, $index)->toArray();

            // Format all instances for the dropdown (only those that matched the filter)
            $instancesData = $instances->map(
                fn (Process $p, int $i): array => ProcessIndexResource::fromModel($p, $organizationId, $i + 1)->toArray()
            )->values()->all();

            $row['instances'] = $instancesData;
            $items->push($row);
        }

        $paginator = new LengthAwarePaginatorImpl(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $paginator->appends(request()->query());
    }
}

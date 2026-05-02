<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Enums\ProcessStatus;
use Src\Domain\Process\Models\Process;

/**
 * @extends Builder<Process>
 */
class ProcessQueryBuilder extends Builder
{
    /**
     * Filter processes by process number.
     *
     * @return $this
     */
    public function whereProcessNumber(string $processNumber): self
    {
        return $this->where('process_number', $processNumber);
    }

    /**
     * Filter processes by process ID (API ID).
     *
     * @return $this
     */
    public function whereProcessId(int $processId): self
    {
        return $this->where('process_id', $processId);
    }

    /**
     * Filter processes that belong to an organization.
     *
     * @return $this
     */
    public function whereOrganization(string $organizationId): self
    {
        return $this->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($organizationId): void {
            $query->where('organizations.id', $organizationId);
        });
    }

    /**
     * Filter active processes for a specific organization.
     *
     * @return $this
     */
    public function whereActiveForOrganization(string $organizationId): self
    {
        return $this->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($organizationId): void {
            $query->where('organization_processes.organization_id', $organizationId)
                ->where('organization_processes.is_active', true);
        });
    }

    /**
     * Filter processes by process number (LIKE search).
     *
     * @return $this
     */
    public function whereProcessNumberLike(string $processNumber): self
    {
        return $this->where('process_number', 'LIKE', "%{$processNumber}%");
    }

    /**
     * Filter processes by court (LIKE search).
     *
     * @return $this
     */
    public function whereCourtLike(string $court): self
    {
        return $this->where('court', 'LIKE', "%{$court}%");
    }

    /**
     * Estado judicial en Rama (columna processes.status): activo en trámite.
     *
     * @return $this
     */
    public function whereJudiciallyActive(): self
    {
        $this->whereIn('status', ['activo', ProcessStatus::ACTIVE->value]);

        return $this;
    }

    /**
     * Estado judicial inactivo/archivado-like en tabla maestra (huérfano sin trámite).
     *
     * @return $this
     */
    public function whereJudiciallyInactive(): self
    {
        $this->whereIn('status', ['inactivo', ProcessStatus::INACTIVE->value]);

        return $this;
    }

    /**
     * Include actions relationship.
     *
     * @return $this
     */
    public function withActions(): self
    {
        return $this->with('actions');
    }

    /**
     * Include subjects relationship with priority order.
     *
     * @return $this
     */
    public function withSubjects(): self
    {
        return $this->with(['subjects' => fn ($q) => $q->orderedByPriority()]);
    }

    /**
     * Include all relationships.
     *
     * @return $this
     */
    public function withRelations(): self
    {
        return $this->with(['actions', 'subjects', 'organizations']);
    }

    /**
     * Order processes by created_at (most recent first).
     *
     * @return $this
     */
    public function orderedByCreatedAt(): self
    {
        return $this->latest('created_at');
    }

    /**
     * Order processes by earliest organization registration date (most recent first).
     * Used for admin views where we want to see all processes ordered by when they were first registered.
     *
     * @return $this
     */
    public function orderedByRegistrationDate(): self
    {
        $this->orderByRaw('(
            SELECT COALESCE(MIN(organization_processes.created_at), processes.created_at)
            FROM organization_processes
            WHERE organization_processes.process_id = processes.id
        ) DESC');

        return $this;
    }

    /**
     * Order processes by the registration date of a specific organization.
     *
     * @return $this
     */
    public function orderByOrganizationRegistration(string $organizationId, string $direction = 'desc'): self
    {
        $this->orderBy(
            \Src\Domain\OrganizationProcess\Models\OrganizationProcess::query()->select('created_at')
                ->whereColumn('process_id', 'processes.id')
                ->where('organization_id', $organizationId)
                ->limit(1),
            $direction
        );

        return $this;
    }

    /**
     * Order processes by process_date (most recent first).
     *
     * @return $this
     */
    public function orderedByProcessDate(): self
    {
        return $this->latest('process_date');
    }

    /**
     * Order processes by last_api_update (most recent first).
     *
     * @return $this
     */
    public function orderedByLastApiUpdate(): self
    {
        return $this->latest('last_api_update');
    }

    /**
     * Order processes by last_activity_date (most recent first).
     *
     * @return $this
     */
    public function orderedByLastActivityDate(): self
    {
        return $this->latest('last_activity_date');
    }

    /**
     * Apply filters to the process query.
     *
     * @param  ProcessFilterData  $data  The filtering criteria.
     * @return $this
     */
    public function filters(ProcessFilterData $data): static
    {
        $this->applyProcessNumberFilter($data->process_number);
        $this->applyCourtFilter($data->court);
        $this->applyProcessClassFilter($data->process_class);
        $this->applyPlaintiffFilter($data->plaintiff);
        $this->applyDefendantFilter($data->defendant);
        $this->applyOrganizationFilter($data->organization);
        $this->applyCreatedAtFilter($data->created_at, $data->created_at_from, $data->created_at_to);
        $this->applyProcessDateFilter($data->process_date, $data->process_date_from, $data->process_date_to);
        $this->applyLastApiUpdateFilter($data->last_api_update_from, $data->last_api_update_to);
        $this->applyStatusFilter($data->status, $data->status_on_process_table);
        $this->applyHasMultipleInstancesFilter($data->has_multiple_instances);
        $this->applyRoleFilter($data->lawyer_role);
        $this->applySeverityColorFilter($data->severity_color);

        return $this;
    }

    /**
     * Apply process number filter.
     */
    private function applyProcessNumberFilter(?string $processNumber): void
    {
        if (! $processNumber) {
            return;
        }

        $this->where('process_number', 'LIKE', "%{$processNumber}%");
    }

    /**
     * Apply court filter.
     */
    private function applyCourtFilter(?string $court): void
    {
        if (! $court) {
            return;
        }

        $this->where('court', 'LIKE', "%{$court}%");
    }

    /**
     * Apply process class filter.
     */
    private function applyProcessClassFilter(?string $processClass): void
    {
        if (! $processClass) {
            return;
        }

        $this->where('process_class', 'LIKE', "%{$processClass}%");
    }

    /**
     * Apply plaintiff filter (searches in subjects by name or identification).
     */
    private function applyPlaintiffFilter(?string $plaintiff): void
    {
        if (! $plaintiff) {
            return;
        }

        $this->whereHas('subjects', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($plaintiff): void {
            $query->where('subject_type', 'Demandante')
                ->where(function (\Illuminate\Contracts\Database\Query\Builder $subQuery) use ($plaintiff): void {
                    $subQuery->where('name_or_business_name', 'LIKE', "%{$plaintiff}%")
                        ->orWhere('identification', 'LIKE', "%{$plaintiff}%");
                });
        });
    }

    /**
     * Apply defendant filter (searches in subjects by name or identification).
     */
    private function applyDefendantFilter(?string $defendant): void
    {
        if (! $defendant) {
            return;
        }

        $this->whereHas('subjects', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($defendant): void {
            $query->where('subject_type', 'Demandado')
                ->where(function (\Illuminate\Contracts\Database\Query\Builder $subQuery) use ($defendant): void {
                    $subQuery->where('name_or_business_name', 'LIKE', "%{$defendant}%")
                        ->orWhere('identification', 'LIKE', "%{$defendant}%");
                });
        });
    }

    /**
     * Apply organization filter (searches in organizations by name).
     */
    private function applyOrganizationFilter(?string $organization): void
    {
        if (! $organization) {
            return;
        }

        $this->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($organization): void {
            $query->where('organizations.name', 'LIKE', "%{$organization}%");
        });
    }

    /**
     * Apply created_at filter (exact date or date range).
     * Filters by organization_processes.created_at (when the organization registered the process).
     */
    private function applyCreatedAtFilter(?string $createdAt, ?string $createdAtFrom, ?string $createdAtTo): void
    {
        if ($createdAt) {
            $this->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($createdAt): void {
                $query->whereDate('organization_processes.created_at', Date::parse($createdAt)->format('Y-m-d'));
            });

            return;
        }

        $this->applyOrganizationProcessDateRangeFilter($createdAtFrom, $createdAtTo);
    }

    /**
     * Apply process_date filter (exact date or date range).
     */
    private function applyProcessDateFilter(?string $processDate, ?string $processDateFrom, ?string $processDateTo): void
    {
        if ($processDate) {
            $this->whereDate('process_date', Date::parse($processDate)->format('Y-m-d'));

            return;
        }

        $this->applyDateRangeFilter('process_date', $processDateFrom, $processDateTo, false);
    }

    /**
     * Apply last_activity_date filter (date range only). Uses query params last_api_update_from / last_api_update_to.
     */
    private function applyLastApiUpdateFilter(?string $lastApiUpdateFrom, ?string $lastApiUpdateTo): void
    {
        $this->applyDateRangeFilter('last_activity_date', $lastApiUpdateFrom, $lastApiUpdateTo, false);
    }

    /**
     * Apply status filter: subscription (pivote) or judicial state on `processes.status` when requested (admin).
     */
    private function applyStatusFilter(?string $status, bool $statusOnProcessTable = false): void
    {
        if (! $status) {
            return;
        }

        $statusEnum = OrganizationProcessStatus::tryFrom($status);
        if (! $statusEnum) {
            return;
        }

        if ($statusOnProcessTable) {
            if ($statusEnum === OrganizationProcessStatus::ACTIVE) {
                $this->whereJudiciallyActive();
            } else {
                $this->whereJudiciallyInactive();
            }

            return;
        }

        $isActive = $statusEnum === OrganizationProcessStatus::ACTIVE;

        $this->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($isActive): void {
            $query->where('organization_processes.is_active', $isActive);
        });
    }

    /**
     * Apply lawyer role filter.
     */
    private function applyRoleFilter(?string $role): void
    {
        if (! $role) {
            return;
        }

        $this->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($role): void {
            $query->where('organization_processes.lawyer_role', $role);
        });
    }

    /**
     * Apply severity color (semaphore) filter.
     */
    private function applySeverityColorFilter(?string $color): void
    {
        if (! $color) {
            return;
        }

        $this->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($color): void {
            $query->where('organization_processes.inactivity_alert_level', $color);
        });
    }

    /**
     * Apply has_multiple_instances filter.
     */
    private function applyHasMultipleInstancesFilter(mixed $hasMultipleInstances): void
    {
        if ($hasMultipleInstances === null || $hasMultipleInstances === '') {
            return;
        }

        $hasMultiple = filter_var($hasMultipleInstances, FILTER_VALIDATE_BOOLEAN);

        $this->where('has_multiple_instances', $hasMultiple);
    }

    /**
     * Apply date range filter helper.
     *
     * @param  string  $column  The column name to filter.
     * @param  string|null  $from  Start date.
     * @param  string|null  $to  End date.
     * @param  bool  $useTime  Whether to use startOfDay/endOfDay or just date.
     */
    private function applyDateRangeFilter(string $column, ?string $from, ?string $to, bool $useTime = false): void
    {
        if (! $from && ! $to) {
            return;
        }

        if ($from && $to) {
            if ($useTime) {
                $this->whereBetween($column, [
                    Date::parse($from)->startOfDay(),
                    Date::parse($to)->endOfDay(),
                ]);
            } else {
                $this->whereBetween($column, [
                    Date::parse($from)->format('Y-m-d'),
                    Date::parse($to)->format('Y-m-d'),
                ]);
            }

            return;
        }

        if ($from) {
            if ($useTime) {
                $this->where($column, '>=', Date::parse($from)->startOfDay());
            } else {
                $this->whereDate($column, '>=', Date::parse($from)->format('Y-m-d'));
            }

            return;
        }

        // $to is set (we already checked !$from && !$to at the beginning)
        if ($useTime) {
            $this->where($column, '<=', Date::parse($to)->endOfDay());
        } else {
            $this->whereDate($column, '<=', Date::parse($to)->format('Y-m-d'));
        }
    }

    /**
     * Apply the date range filter for the organization_processes pivot table.
     * Filters by when the organization registered the process.
     *
     * @param  string|null  $from  Start date.
     * @param  string|null  $to  End date.
     */
    private function applyOrganizationProcessDateRangeFilter(?string $from, ?string $to): void
    {
        if (! $from && ! $to) {
            return;
        }

        $this->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($from, $to): void {
            if ($from && $to) {
                $query->whereBetween('organization_processes.created_at', [
                    Date::parse($from)->startOfDay(),
                    Date::parse($to)->endOfDay(),
                ]);
            } elseif ($from) {
                $query->whereDate('organization_processes.created_at', '>=', Date::parse($from)->format('Y-m-d'));
            } elseif ($to !== '' && $to !== '0') { // @phpstan-ignore-line
                $query->whereDate('organization_processes.created_at', '<=', Date::parse($to)->format('Y-m-d'));
            }
        });
    }
}

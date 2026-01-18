<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Application\AppUser\Process\Data\ProcessFilterData;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;

/**
 * @extends Builder<\Src\Domain\Process\Models\Process>
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
     * Include actions relationship.
     *
     * @return $this
     */
    public function withActions(): self
    {
        return $this->with('actions');
    }

    /**
     * Include subjects relationship.
     *
     * @return $this
     */
    public function withSubjects(): self
    {
        return $this->with('subjects');
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
     * Order processes by process_date (most recent first).
     *
     * @return $this
     */
    public function orderedByProcessDate(): self
    {
        return $this->latest('process_date');
    }

    /**
     * Apply filters to the process query.
     *
     * @param  ProcessFilterData  $data  The filtering criteria.
     * @return $this
     */
    public function filters(ProcessFilterData $data): self
    {
        return $this
            ->when($data->process_number, function ($query, $processNumber): void {
                $query->where('process_number', 'LIKE', "%{$processNumber}%");
            })
            ->when($data->created_at, function ($query, $createdAt): void {
                $query->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($createdAt): void {
                    $q->whereDate('organization_processes.created_at', \Illuminate\Support\Facades\Date::parse($createdAt)->format('Y-m-d'));
                });
            })
            ->when($data->created_at_from || $data->created_at_to, function ($query) use ($data): void {
                // Filtrar por rango de created_at del pivot organization_processes
                if ($data->created_at_from && $data->created_at_to) {
                    $query->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($data): void {
                        $q->whereBetween('organization_processes.created_at', [
                            \Illuminate\Support\Facades\Date::parse($data->created_at_from)->startOfDay(),
                            \Illuminate\Support\Facades\Date::parse($data->created_at_to)->endOfDay(),
                        ]);
                    });
                } elseif ($data->created_at_from) {
                    // Solo fecha desde
                    $query->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($data): void {
                        $q->whereDate('organization_processes.created_at', '>=', \Illuminate\Support\Facades\Date::parse($data->created_at_from)->format('Y-m-d'));
                    });
                } elseif ($data->created_at_to) {
                    // Solo fecha hasta
                    $query->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($data): void {
                        $q->whereDate('organization_processes.created_at', '<=', \Illuminate\Support\Facades\Date::parse($data->created_at_to)->format('Y-m-d'));
                    });
                }
            })
            ->when($data->process_date, function ($query, \DateTimeInterface|\Carbon\WeekDay|\Carbon\Month|string|int|float|null $processDate): void {
                $query->whereDate('process_date', \Illuminate\Support\Facades\Date::parse($processDate)->format('Y-m-d'));
            })
            ->when($data->process_date_from || $data->process_date_to, function ($query) use ($data): void {
                if ($data->process_date_from && $data->process_date_to) {
                    $query->whereBetween('process_date', [
                        \Illuminate\Support\Facades\Date::parse($data->process_date_from)->format('Y-m-d'),
                        \Illuminate\Support\Facades\Date::parse($data->process_date_to)->format('Y-m-d'),
                    ]);
                } elseif ($data->process_date_from) {
                    $query->whereDate('process_date', '>=', \Illuminate\Support\Facades\Date::parse($data->process_date_from)->format('Y-m-d'));
                } elseif ($data->process_date_to) {
                    $query->whereDate('process_date', '<=', \Illuminate\Support\Facades\Date::parse($data->process_date_to)->format('Y-m-d'));
                }
            })
            ->when($data->status, function ($query, $status): void {
                $statusEnum = OrganizationProcessStatus::tryFrom($status);
                if ($statusEnum) {
                    $isActive = $statusEnum === OrganizationProcessStatus::ACTIVE;
                    $query->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($isActive): void {
                        $q->where('organization_processes.is_active', $isActive);
                    });
                }
            })
            ->when($data->is_private !== null && $data->is_private !== '', function ($query) use ($data): void {
                $isPrivate = filter_var($data->is_private, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_private', $isPrivate);
            })
            ->when($data->has_multiple_instances !== null && $data->has_multiple_instances !== '', function ($query) use ($data): void {
                $hasMultiple = filter_var($data->has_multiple_instances, FILTER_VALIDATE_BOOLEAN);
                $query->where('has_multiple_instances', $hasMultiple);
            });
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;

/**
 * Shared organization_processes constraints for process/action export queries.
 */
final class OrganizationProcessExportPivotFilter
{
    public function apply(Builder $query, string $organizationId, OrganizationProcessExportData $filters): void
    {
        $query->where('organizations.id', $organizationId);

        if ($filters->status) {
            $statusEnum = OrganizationProcessStatus::tryFrom($filters->status);
            if ($statusEnum instanceof OrganizationProcessStatus) {
                $query->where('organization_processes.status', $statusEnum->value);
            }
        }

        if ($filters->created_at_from) {
            $query->where(
                'organization_processes.created_at',
                '>=',
                Carbon::parse($filters->created_at_from)->startOfDay()
            );
        }

        if ($filters->created_at_to) {
            $query->where(
                'organization_processes.created_at',
                '<=',
                Carbon::parse($filters->created_at_to)->endOfDay()
            );
        }

        if ($filters->updated_at_from) {
            $query->where(
                'organization_processes.updated_at',
                '>=',
                Carbon::parse($filters->updated_at_from)->startOfDay()
            );
        }

        if ($filters->updated_at_to) {
            $query->where(
                'organization_processes.updated_at',
                '<=',
                Carbon::parse($filters->updated_at_to)->endOfDay()
            );
        }
    }
}

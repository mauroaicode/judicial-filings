<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\Process;

/**
 * Expands one or more process UUIDs to every org-linked instance that shares
 * the same radicado (process_number), matching trash / cupo semantics.
 */
readonly class ResolveOrganizationProcessIdsForRadicadoService
{
    /**
     * @param  list<string>  $processIds
     * @return list<string>
     */
    public function handle(string $organizationId, array $processIds): array
    {
        if ($processIds === []) {
            return [];
        }

        $uniqueIds = array_values(array_unique($processIds));

        $processNumbers = Process::query()
            ->whereIn('id', $uniqueIds)
            ->pluck('process_number')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($processNumbers === []) {
            return $uniqueIds;
        }

        return OrganizationProcess::query()
            ->where('organization_id', $organizationId)
            ->whereHas('process', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($processNumbers): void {
                $query->whereIn('process_number', $processNumbers);
            })
            ->pluck('process_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }
}

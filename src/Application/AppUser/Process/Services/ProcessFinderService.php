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
        $filteredIdsQuery = Process::query()
            ->whereOrganization($organizationId)
            ->filters($filters)
            ->select('id');

        $total = Process::query()
            ->whereIn('id', $filteredIdsQuery)
            ->selectRaw('COUNT(DISTINCT process_number) as total')
            ->value('total') ?? 0;

        $processNumbers = Process::query()
            ->whereIn('id', (clone $filteredIdsQuery))
            ->selectRaw('process_number, MAX(last_activity_date) as max_activity_date')
            ->groupBy('process_number')
            ->orderByDesc('max_activity_date')
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
            ->whereOrganization($organizationId)
            ->whereIn('process_number', $processNumbers)
            ->with(['subjects', 'organizations' => function ($query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            }])
            ->orderedByLastActivityDate()
            ->get();

        $byNumber = $processes->groupBy('process_number');
        $startIndex = (($page - 1) * $perPage) + 1;
        $items = collect();

        foreach ($processNumbers as $position => $processNumber) {
            $instances = $byNumber->get($processNumber, collect())->values();
            $representative = $instances->first();
            if (! $representative instanceof Process) {
                continue;
            }

            $index = $startIndex + $position;
            $row = ProcessIndexResource::fromModel($representative, $organizationId, $index)->toArray();
            $instancesData = $instances->map(fn (Process $p, int $i): array => ProcessIndexResource::fromModel($p, $organizationId, $i + 1)->toArray())->values()->all();
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

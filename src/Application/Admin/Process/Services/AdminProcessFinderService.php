<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Src\Application\Admin\Process\Resources\AdminProcessIndexResource;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\Process\Models\Process;

readonly class AdminProcessFinderService
{
    /**
     * Get paginated processes grouped by radicado (process_number): one row per radicado,
     * with the representative instance being the one with the latest last_activity_date.
     * Each item includes an `instances` array with all instances (same shape as the row)
     * for the frontend dropdown, ordered by last_activity_date DESC.
     *
     * @param  ProcessFilterData  $filters  The filtering criteria.
     * @param  int  $perPage  Number of radicados per page.
     * @param  int  $page  Current page (1-based).
     */
    public function handle(ProcessFilterData $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $filteredIdsQuery = Process::query()
            ->filters($filters)
            ->select('processes.id');

        $total = Process::query()
            ->whereIn('processes.id', $filteredIdsQuery)
            ->selectRaw('COUNT(DISTINCT process_number) as total')
            ->value('total') ?? 0;

        $processNumbers = Process::query()
            ->whereIn('processes.id', (clone $filteredIdsQuery))
            ->join('organization_processes', 'processes.id', '=', 'organization_processes.process_id')
            ->whereNull('organization_processes.deleted_at')
            ->selectRaw('processes.process_number, MAX(organization_processes.created_at) as max_reg')
            ->groupBy('processes.process_number')
            ->orderByDesc('max_reg')
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
            ->with(['subjects', 'organizations', 'processDataSource'])
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
            $row = AdminProcessIndexResource::fromModel($representative, $index)->toArray();
            $instancesData = $instances->map(fn (Process $p, int $i): array => AdminProcessIndexResource::fromModel($p, $i + 1)->toArray())->values()->all();
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

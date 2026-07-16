<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\Admin\JudicialSync\Data\AdminJudicialSyncHistoryFilterData;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;
use Src\Domain\JudicialSync\QueryBuilders\JudicialSyncRunQueryBuilder;

readonly class AdminListJudicialSyncRunsService
{
    /**
     * @return LengthAwarePaginator<int, JudicialSyncRun>
     */
    public function handle(AdminJudicialSyncHistoryFilterData $filters): LengthAwarePaginator
    {
        $query = $this->buildBaseQuery();
        $this->applyStatusFilter($query, $filters->status);
        $this->applyDataSourceFilter($query, $filters->data_source);
        $this->applyStartedAtRangeFilter($query, $filters->started_at_from, $filters->started_at_to);

        return $query->paginate($filters->per_page);
    }

    private function buildBaseQuery(): JudicialSyncRunQueryBuilder
    {
        return JudicialSyncRun::query()
            ->withQueuePendingJobs()
            ->orderedByCreatedAtDesc();
    }

    private function applyStatusFilter(JudicialSyncRunQueryBuilder $query, ?string $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $query->whereStatusValue($status);
    }

    private function applyDataSourceFilter(JudicialSyncRunQueryBuilder $query, ?string $dataSource): void
    {
        if ($dataSource === null || $dataSource === '') {
            return;
        }

        $query->whereDataSourceValue($dataSource);
    }

    private function applyStartedAtRangeFilter(JudicialSyncRunQueryBuilder $query, ?string $from, ?string $to): void
    {
        $query->whereStartedAtBetween($from, $to);
    }
}

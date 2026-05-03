<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Process\Data\AdminProcessImportHistoryFilterData;
use Src\Domain\Process\Models\ProcessImportBatch;
use Src\Domain\Process\QueryBuilders\ProcessImportBatchQueryBuilder;

readonly class AdminListProcessImportHistoryService
{
    /**
     * @return LengthAwarePaginator<int, ProcessImportBatch>
     */
    public function handle(AdminProcessImportHistoryFilterData $filters): LengthAwarePaginator
    {
        $query = $this->buildBaseQuery();
        $this->applyOrganizationNameFilter($query, $filters->organization);
        $this->applyFileNameFilter($query, $filters->file_name);
        $this->applyStatusFilter($query, $filters->status);
        $this->applyHasErrorsFilter($query, $filters->has_errors);
        $this->applyCreatedAtRangeFilter($query, $filters->created_at_from, $filters->created_at_to);

        return $query->paginate($filters->per_page);
    }

    private function buildBaseQuery(): ProcessImportBatchQueryBuilder
    {
        return ProcessImportBatch::query()
            ->withOrganizationDetails()
            ->orderedByCreatedAt();
    }

    private function applyOrganizationNameFilter(ProcessImportBatchQueryBuilder $query, ?string $organization): void
    {
        $query->whereOrganizationNameLike($organization);
    }

    private function applyFileNameFilter(ProcessImportBatchQueryBuilder $query, ?string $fileName): void
    {
        $query->whereFileNameLike($fileName);
    }

    private function applyStatusFilter(ProcessImportBatchQueryBuilder $query, ?string $status): void
    {
        if (! $status) {
            return;
        }

        $query->whereStatus($status);
    }

    private function applyHasErrorsFilter(ProcessImportBatchQueryBuilder $query, mixed $hasErrors): void
    {
        if ($hasErrors === null || $hasErrors === '') {
            return;
        }

        $flag = filter_var($hasErrors, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($flag === null) {
            return;
        }

        if ($flag) {
            $query->whereHasRecordedErrors();
        } else {
            $query->whereHasNoRecordedErrors();
        }
    }

    private function applyCreatedAtRangeFilter(ProcessImportBatchQueryBuilder $query, ?string $from, ?string $to): void
    {
        $query->whereCreatedAtBetween($from, $to);
    }
}

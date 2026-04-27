<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Process\Data\ProcessImportFilterData;
use Src\Domain\Process\Models\ProcessImportBatch;
use Src\Domain\Process\QueryBuilders\ProcessImportBatchQueryBuilder;

class ListProcessImportHistoryService
{
    /**
     * Retrieve a paginated list of import batches for the given organization.
     *
     * @return LengthAwarePaginator<int, ProcessImportBatch>
     */
    public function handle(string $organizationId, ProcessImportFilterData $filters): LengthAwarePaginator
    {
        $query = $this->buildQuery($organizationId);

        if ($filters->status) {
            $query->whereStatus($filters->status);
        }

        return $query->paginate($filters->per_page);
    }

    /**
     * Build the base query scoped to the organization, ordered most recent first.
     */
    private function buildQuery(string $organizationId): ProcessImportBatchQueryBuilder
    {
        return ProcessImportBatch::query()
            ->whereOrganization($organizationId)
            ->orderedByCreatedAt();
    }
}

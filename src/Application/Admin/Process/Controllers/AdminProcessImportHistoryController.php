<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Process\Data\AdminProcessImportHistoryFilterData;
use Src\Application\Admin\Process\Resources\AdminProcessImportBatchResource;
use Src\Application\Admin\Process\Services\AdminListProcessImportHistoryService;
use Src\Domain\Process\Models\ProcessImportBatch;

readonly class AdminProcessImportHistoryController
{
    public function __construct(
        private AdminListProcessImportHistoryService $adminListProcessImportHistoryService,
    ) {}

    /**
     * Paginated import batches across all organizations (admin).
     *
     * Query parameters:
     * - `organization` — partial match on organization name (LIKE).
     * - `file_name` — partial match on uploaded Excel file name (LIKE).
     * - `status` — exact batch status: `processing`, `completed`, `failed` ({@see \Src\Domain\Process\Enums\ProcessImportBatchStatus}).
     * - `has_errors` — boolean (`true`/`1`/`false`/`0`): `true` = batches with non-empty `errors` JSON; `false` = empty or null errors.
     * - `created_at_from` — inclusive lower bound for batch `created_at` (date `Y-m-d`).
     * - `created_at_to` — inclusive upper bound for batch `created_at` (date `Y-m-d`).
     * - `per_page` — page size (default 15).
     */
    public function index(AdminProcessImportHistoryFilterData $filters): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, ProcessImportBatch> $paginator */
        $paginator = $this->adminListProcessImportHistoryService->handle($filters);

        $paginator->through(
            fn (ProcessImportBatch $batch): AdminProcessImportBatchResource => AdminProcessImportBatchResource::fromModel($batch)
        );

        return $paginator;
    }
}

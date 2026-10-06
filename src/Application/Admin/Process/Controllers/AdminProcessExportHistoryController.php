<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Process\Data\AdminProcessExportHistoryFilterData;
use Src\Application\Admin\Process\Resources\AdminProcessExportHistoryResource;
use Src\Application\Admin\Process\Services\AdminListProcessExportHistoryService;
use Src\Domain\Process\Models\ProcessExport;

readonly class AdminProcessExportHistoryController
{
    public function __construct(
        private AdminListProcessExportHistoryService $listProcessExportHistoryService,
    ) {}

    /**
     * Paginated export history across all organizations (admin).
     *
     * Query parameters:
     * - `organization` — partial match on organization name (LIKE).
     * - `organization_id` — exact organization UUID.
     * - `status` — `pending`, `processing`, `completed`, `failed`.
     * - `created_at_from` / `created_at_to` — date range on `created_at`.
     * - `per_page` — page size (default 15).
     */
    public function index(AdminProcessExportHistoryFilterData $filters): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, ProcessExport> $paginator */
        $paginator = $this->listProcessExportHistoryService->handle($filters);

        $paginator->through(
            fn (ProcessExport $export): AdminProcessExportHistoryResource => AdminProcessExportHistoryResource::fromModel($export)
        );

        return $paginator;
    }
}

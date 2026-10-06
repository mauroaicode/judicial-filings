<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Process\Data\AdminProcessExportHistoryFilterData;
use Src\Domain\Process\Models\ProcessExport;

readonly class AdminListProcessExportHistoryService
{
    /**
     * @return LengthAwarePaginator<int, ProcessExport>
     */
    public function handle(AdminProcessExportHistoryFilterData $filters): LengthAwarePaginator
    {
        $query = ProcessExport::query()
            ->withDetails()
            ->orderedByCreatedAtDesc()
            ->whereStatus($filters->status)
            ->whereOrganizationNameLike($filters->organization)
            ->whereCreatedAtBetween($filters->created_at_from, $filters->created_at_to);

        if ($filters->organization_id) {
            $query->whereOrganization($filters->organization_id);
        }

        return $query->paginate($filters->per_page);
    }
}

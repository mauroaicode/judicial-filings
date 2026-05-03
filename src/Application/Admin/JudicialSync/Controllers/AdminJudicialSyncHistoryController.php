<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\JudicialSync\Data\AdminJudicialSyncHistoryFilterData;
use Src\Application\Admin\JudicialSync\Resources\JudicialSyncRunResource;
use Src\Application\Admin\JudicialSync\Services\AdminListJudicialSyncRunsService;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

readonly class AdminJudicialSyncHistoryController
{
    public function __construct(
        private AdminListJudicialSyncRunsService $adminListJudicialSyncRunsService,
    ) {}

    /**
     * Paginated history of `judicial:sync-processes` runs.
     *
     * Ordered by `created_at` descending (newest first). Each item includes `moment_of_day` (`mañana` | `tarde` |
     * `noche`) from `started_at` in {@see config('app.timezone')}, plus `status` (enum value) and `status_label`
     * (translated via `enums.judicial_sync_run_status`).
     *
     * Query: `status`, `started_at_from`, `started_at_to`, `per_page`.
     */
    public function index(AdminJudicialSyncHistoryFilterData $filters): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, JudicialSyncRun> $paginator */
        $paginator = $this->adminListJudicialSyncRunsService->handle($filters);

        $paginator->through(
            fn (JudicialSyncRun $run): JudicialSyncRunResource => JudicialSyncRunResource::fromModel($run)
        );

        return $paginator;
    }
}

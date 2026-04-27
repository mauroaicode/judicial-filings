<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Process\Data\ProcessImportFilterData;
use Src\Application\AppUser\Process\Resources\ProcessImportBatchResource;
use Src\Application\AppUser\Process\Services\ListProcessImportHistoryService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Models\ProcessImportBatch;

readonly class ProcessImportHistoryController
{
    public function __construct(
        private ListProcessImportHistoryService $listProcessImportHistoryService,
    ) {}

    /**
     * Return a paginated list of process import batches for the authenticated user's organization.
     */
    public function index(ProcessImportFilterData $filters): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        /** @var LengthAwarePaginator<int, ProcessImportBatch> $paginator */
        $paginator = $this->listProcessImportHistoryService->handle($organization->id, $filters);

        $paginator->through(
            fn (ProcessImportBatch $batch): ProcessImportBatchResource => ProcessImportBatchResource::fromModel($batch)
        );

        return $paginator;
    }
}

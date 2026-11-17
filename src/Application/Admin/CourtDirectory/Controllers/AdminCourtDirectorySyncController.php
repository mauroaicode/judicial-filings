<?php

declare(strict_types=1);

namespace Src\Application\Admin\CourtDirectory\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\CourtDirectory\Resources\CourtDirectorySyncRunResource;
use Src\Application\Admin\CourtDirectory\Services\ListCourtDirectorySyncRunsService;
use Src\Application\Shared\CourtDirectory\Services\QueueCourtDirectorySyncService;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncStatus;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncTrigger;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;
use Throwable;

readonly class AdminCourtDirectorySyncController
{
    public function __construct(
        private QueueCourtDirectorySyncService $queueCourtDirectorySyncService,
        private ListCourtDirectorySyncRunsService $listCourtDirectorySyncRunsService,
    ) {}

    public function sync(): JsonResponse
    {
        try {
            $run = $this->queueCourtDirectorySyncService->handle(CourtDirectorySyncTrigger::Admin);
        } catch (Throwable $exception) {
            $run = CourtDirectorySyncRun::query()->latest()->first();

            if (! $run instanceof CourtDirectorySyncRun) {
                throw $exception;
            }
        }

        $status = match ($run->status) {
            CourtDirectorySyncStatus::Succeeded => 200,
            CourtDirectorySyncStatus::Failed => 502,
            default => 202,
        };

        return response()->json(CourtDirectorySyncRunResource::fromModel($run)->toArray(), $status);
    }

    /**
     * @return LengthAwarePaginator<int, CourtDirectorySyncRunResource>
     */
    public function index(): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, CourtDirectorySyncRun> $paginator */
        $paginator = $this->listCourtDirectorySyncRunsService->handle();

        $paginator->through(
            fn (CourtDirectorySyncRun $run): CourtDirectorySyncRunResource => CourtDirectorySyncRunResource::fromModel($run)
        );

        /** @var LengthAwarePaginator<int, CourtDirectorySyncRunResource> $paginator */
        return $paginator;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Src\Application\AppUser\Process\Data\ProcessActionFilterData;
use Src\Application\AppUser\Process\Resources\ProcessActionResource;
use Src\Application\AppUser\Process\Services\ProcessActionFinderService;
use Src\Application\AppUser\Process\Services\ProcessDetailService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

readonly class ProcessActionController
{
    public function __construct(
        private ProcessActionFinderService $processActionFinderService,
        private ProcessDetailService $processDetailService
    ) {}

    /**
     * Display a listing of process actions for the specified process.
     */
    public function index(Request $request, string $processId): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $process = $this->processDetailService->handle($processId, $organization->id);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $filters = ProcessActionFilterData::from($request->query());
        $perPage = (int) $request->query('per_page', 5);

        $paginatedActions = $this->processActionFinderService->handle($processId, $filters, $perPage);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginatedActions */
        $transformedItems = $paginatedActions->getCollection()->map(fn (ProcessAction $action): array => ProcessActionResource::fromModel($action)->toArray());

        $paginatedActions->setCollection($transformedItems);

        return $paginatedActions;
    }
}

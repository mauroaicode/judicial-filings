<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Src\Application\Admin\Process\Resources\AdminProcessIndexResource;
use Src\Application\Admin\Process\Services\AdminProcessFinderService;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\Process\Models\Process;

readonly class ProcessController
{
    public function __construct(
        private AdminProcessFinderService $adminProcessFinderService
    ) {}

    /**
     * Display a listing of all processes from all organizations.
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $filters = ProcessFilterData::from($request->query());
        $perPage = (int) $request->query('per_page', 20);

        $paginatedProcesses = $this->adminProcessFinderService->handle($filters, $perPage);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginatedProcesses */
        $currentPage = $paginatedProcesses->currentPage();
        $startIndex = (($currentPage - 1) * $perPage) + 1;

        $transformedItems = $paginatedProcesses->getCollection()->map(function (Process $process, int $key) use ($startIndex): array {
            $index = $startIndex + $key;

            return AdminProcessIndexResource::fromModel($process, $index)->toArray();
        });

        $paginatedProcesses->setCollection($transformedItems);

        return $paginatedProcesses;
    }
}

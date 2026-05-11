<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Src\Application\Admin\Process\Services\AdminProcessFinderService;
use Src\Application\Shared\Data\ProcessFilterData;

readonly class ProcessController
{
    public function __construct(
        private AdminProcessFinderService $adminProcessFinderService
    ) {}

    /**
     * Display a listing of all processes from all organizations.
     * Grouped by radicado (one row per process_number) with instances array for multiple instances.
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $filters = ProcessFilterData::validateAndCreate(array_merge($request->query(), [
            'status_on_process_table' => true,
        ]));
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        return $this->adminProcessFinderService->handle($filters, $perPage, $page);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Dashboard\Controllers;

use Illuminate\Http\Request;
use Src\Application\Admin\Dashboard\Resources\AdminDashboardStatsResource;
use Src\Application\Admin\Dashboard\Services\AdminDashboardStatsService;
use Src\Application\Shared\Data\ProcessFilterData;

readonly class AdminDashboardStatsController
{
    public function __construct(
        private AdminDashboardStatsService $adminDashboardStatsService
    ) {}

    /**
     * Admin process KPIs. Accepts the same query filters as GET /api/admin/processes
     * (organization_id, organization, process_number, status, privacy, etc.).
     */
    public function index(Request $request): AdminDashboardStatsResource
    {
        $filters = ProcessFilterData::validateAndCreate(array_merge($request->query(), [
            'status_on_process_table' => true,
        ]));

        return $this->adminDashboardStatsService->handle($filters);
    }
}

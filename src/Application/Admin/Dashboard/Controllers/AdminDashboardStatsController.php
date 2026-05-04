<?php

declare(strict_types=1);

namespace Src\Application\Admin\Dashboard\Controllers;

use Src\Application\Admin\Dashboard\Resources\AdminDashboardStatsResource;
use Src\Application\Admin\Dashboard\Services\AdminDashboardStatsService;

readonly class AdminDashboardStatsController
{
    public function __construct(
        private AdminDashboardStatsService $adminDashboardStatsService
    ) {}

    public function index(): AdminDashboardStatsResource
    {
        return $this->adminDashboardStatsService->handle();
    }
}

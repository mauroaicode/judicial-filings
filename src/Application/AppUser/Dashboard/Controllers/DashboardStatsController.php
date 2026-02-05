<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Dashboard\Services\DashboardStatsService;
use Src\Domain\AppUser\Models\AppUser;

readonly class DashboardStatsController
{
    public function __construct(
        private DashboardStatsService $dashboardStatsService
    ) {}

    public function index(): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $stats = $this->dashboardStatsService->handle($organization->id);

        return response()->json($stats->toArray());
    }
}

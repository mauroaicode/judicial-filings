<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\AppUser\Dashboard\Services\DashboardStatsService;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\AppUser\Models\AppUser;

readonly class DashboardStatsController
{
    public function __construct(
        private DashboardStatsService $dashboardStatsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $filters = ProcessFilterData::validateAndCreate($request->query());

        $stats = $this->dashboardStatsService->handle($organization->id, $filters);

        return response()->json($stats->toArray());
    }
}

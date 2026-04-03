<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Dashboard\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\AppUser\Dashboard\Services\DashboardSummaryService;
use Src\Domain\AppUser\Models\AppUser;

readonly class DashboardController
{
    public function __construct(
        private DashboardSummaryService $dashboardSummaryService
    ) {}

    /**
     * Get the overview summary for the dashboard (KPIs and Alerts).
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $summary = $this->dashboardSummaryService->handle(
            $organization->id,
            $dateFrom ? (string) $dateFrom : null,
            $dateTo ? (string) $dateTo : null
        );

        return response()->json($summary->toArray());
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Notification\Data\DashboardSummaryData;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\AppUser\Notification\Resources\NotificationDigestResource;
use Src\Application\AppUser\Notification\Services\DashboardSummaryService;
use Src\Application\AppUser\Notification\Services\NotificationDigestFinderService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\NotificationDigest;

readonly class NotificationDigestController
{
    public function __construct(
        private NotificationDigestFinderService $notificationDigestFinderService,
        private DashboardSummaryService $dashboardSummaryService
    ) {}

    public function index(NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        /** @var LengthAwarePaginator $paginatedDigests */
        $paginatedDigests = $this->notificationDigestFinderService->handle($filters, $organization->id, $filters->per_page);

        $mergedCollection = $paginatedDigests->getCollection()
            ->groupBy(fn ($digest) => $digest->created_at->format('Y-m-d'))
            ->map(function ($group) use ($filters): array {

                /** @var NotificationDigest $mergedDigest */
                $mergedDigest = $group->first();

                if ($group->count() > 1) {
                    $combinedData = [];
                    $combinedNotifications = collect();

                    foreach ($group as $digest) {
                        $combinedData = array_merge($combinedData, is_array($digest->data) ? $digest->data : []);
                        if ($digest->relationLoaded('notifications')) {
                            $combinedNotifications = $combinedNotifications->merge($digest->notifications);
                        }
                    }

                    $mergedDigest->data = $combinedData;
                    $mergedDigest->setRelation('notifications', $combinedNotifications);
                }

                return NotificationDigestResource::fromModel($mergedDigest, $filters)->toArray();
            })
            ->values();

        $paginatedDigests->setCollection($mergedCollection);

        return $paginatedDigests;
    }

    /**
     * Get the dashboard summary (KPI cards).
     */
    public function summary(Request $request): DashboardSummaryData
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        return $this->dashboardSummaryService->handle(
            $organization->id,
            is_string($dateFrom) ? $dateFrom : null,
            is_string($dateTo) ? $dateTo : null
        );
    }
}

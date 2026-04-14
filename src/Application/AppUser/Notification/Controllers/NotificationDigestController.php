<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\AppUser\Notification\Resources\NotificationDigestResource;
use Src\Application\AppUser\Notification\Services\NotificationDigestFinderService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\NotificationDigest;

readonly class NotificationDigestController
{
    public function __construct(
        private NotificationDigestFinderService $notificationDigestFinderService,
        private \Src\Domain\Process\Services\GroupProcessActionsService $groupProcessActionsService
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

                $resource = NotificationDigestResource::fromModel($mergedDigest, $filters)->toArray();

                // Agrupamos las actuaciones dentro del digest
                if (isset($resource['data']) && is_array($resource['data'])) {
                    $resource['data'] = $this->groupProcessActionsService->handle(collect($resource['data']))->toArray();
                }

                return $resource;
            })
            ->values();

        $paginatedDigests->setCollection($mergedCollection);

        return $paginatedDigests;
    }
}

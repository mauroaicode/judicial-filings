<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\AppUser\Notification\Resources\NotificationDigestResource;
use Src\Application\AppUser\Notification\Services\NotificationDigestFinderService;
use Src\Domain\AppUser\Models\AppUser;

readonly class NotificationDigestController
{
    public function __construct(
        private NotificationDigestFinderService $notificationDigestFinderService
    ) {}

    public function index(NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $paginatedDigests = $this->notificationDigestFinderService->handle($filters, $organization->id, $filters->per_page);

        // Agrupar consolidados por día y unir sus contenidos (data y relaciones)
        $mergedCollection = $paginatedDigests->getCollection()
            ->groupBy(fn ($digest) => $digest->created_at->format('Y-m-d'))
            ->map(function ($group) use ($filters) {
                // Tomamos el primero como base
                /** @var \Src\Domain\Notification\Models\NotificationDigest $mergedDigest */
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

                    // Actualizar el objeto base con la información combinada
                    $mergedDigest->data = $combinedData;
                    $mergedDigest->setRelation('notifications', $combinedNotifications);
                }

                return NotificationDigestResource::fromModel($mergedDigest, $filters)->toArray();
            })
            ->values();

        $paginatedDigests->setCollection($mergedCollection);

        return $paginatedDigests;
    }
}

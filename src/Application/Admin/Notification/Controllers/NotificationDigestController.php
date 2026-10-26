<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\PaginatedDataCollection;
use Src\Application\Admin\Notification\Data\NotificationDigestFilterData;
use Src\Application\Admin\Notification\Resources\NotificationDigestHistoryResource;
use Src\Application\Admin\Notification\Services\ListNotificationDigestsService;
use Src\Domain\Notification\Models\NotificationDigest;

readonly class NotificationDigestController
{
    public function __construct(
        private ListNotificationDigestsService $listNotificationDigestsService,
    ) {}

    /**
     * @return PaginatedDataCollection<int, NotificationDigestHistoryResource>
     */
    public function index(NotificationDigestFilterData $filters): PaginatedDataCollection
    {
        /** @var LengthAwarePaginator<int, NotificationDigest> $digests */
        $digests = $this->listNotificationDigestsService->handle($filters);

        $digests->through(
            fn (NotificationDigest $digest): NotificationDigestHistoryResource => NotificationDigestHistoryResource::fromModel($digest),
        );

        return NotificationDigestHistoryResource::collect($digests, PaginatedDataCollection::class);
    }
}

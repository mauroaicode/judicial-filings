<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Controllers;

use Spatie\LaravelData\DataCollection;
use Src\Application\Admin\Notification\Resources\NotificationDigestViewResource;
use Src\Application\Admin\Notification\Services\ListNotificationDigestViewsService;
use Src\Domain\Notification\Models\NotificationDigest;

readonly class NotificationDigestViewController
{
    public function __construct(
        private ListNotificationDigestViewsService $listNotificationDigestViewsService,
    ) {}

    /**
     * @return DataCollection<int, NotificationDigestViewResource>
     */
    public function index(NotificationDigest $digest): DataCollection
    {
        $views = $this->listNotificationDigestViewsService->handle($digest);

        return NotificationDigestViewResource::collect($views, DataCollection::class)->wrap('data');
    }
}

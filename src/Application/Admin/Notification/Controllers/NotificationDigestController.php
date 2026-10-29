<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\PaginatedDataCollection;
use Src\Application\Admin\Notification\Data\NotificationDigestFilterData;
use Src\Application\Admin\Notification\Resources\NotificationDigestHistoryResource;
use Src\Application\Admin\Notification\Services\ListNotificationDigestsService;
use Src\Application\AppUser\Notification\Services\ExportNotificationDigestPdfService;
use Src\Domain\Notification\Models\NotificationDigest;
use Symfony\Component\HttpFoundation\Response;

readonly class NotificationDigestController
{
    public function __construct(
        private ListNotificationDigestsService $listNotificationDigestsService,
        private ExportNotificationDigestPdfService $exportNotificationDigestPdfService,
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

    /**
     * Download the same consolidado PDF used by the app user.
     */
    public function exportPdf(NotificationDigest $digest): Response
    {
        return $this->exportNotificationDigestPdfService->handle($digest->organization_id, $digest->id);
    }
}

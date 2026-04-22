<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Domain\Notification\Models\NotificationDigest;

class ListNotificationDigestHistoryService
{
    /**
     * Handle the request to list notification digest history for an organization.
     *
     * @return LengthAwarePaginator<int, NotificationDigest>
     */
    public function handle(string $organizationId, NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        $query = NotificationDigest::query()
            ->whereOrganization($organizationId)
            ->select(['id', 'created_at', 'data', 'email_sent_at', 'whatsapp_sent_at', 'sms_sent_at']);

        $query->filters($filters);

        return $query->paginate($filters->per_page);
    }
}

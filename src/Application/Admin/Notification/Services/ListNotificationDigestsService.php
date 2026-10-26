<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Notification\Data\NotificationDigestFilterData;
use Src\Application\Shared\Notification\NotificationDigestAccess;
use Src\Domain\Notification\Models\NotificationDigest;

class ListNotificationDigestsService
{
    /**
     * Page ids first, then load `data` for that page only.
     *
     * Ordering a select that includes the JSON `data` column makes MySQL filesort
     * every digest payload and fails with "Out of sort memory" once the table grows.
     * The admin list has no organization filter, so the (organization_id, created_at)
     * index cannot serve `ORDER BY created_at`.
     *
     * @return LengthAwarePaginator<int, NotificationDigest>
     */
    public function handle(NotificationDigestFilterData $filters): LengthAwarePaginator
    {
        $pageQuery = NotificationDigest::query()->select('notification_digests.id');

        if (is_string($filters->organization_id) && $filters->organization_id !== '') {
            $pageQuery->whereOrganization($filters->organization_id);
        }

        $pageQuery->filters(NotificationDigestAccess::filters([
            'process_number' => $filters->process_number,
            'registration_date_from' => $filters->registration_date_from,
            'registration_date_to' => $filters->registration_date_to,
            'action_date_from' => $filters->action_date_from,
            'action_date_to' => $filters->action_date_to,
            'created_at_from' => $filters->created_at_from,
            'created_at_to' => $filters->created_at_to,
            'term_start_date_from' => $filters->term_start_date_from,
            'term_start_date_to' => $filters->term_start_date_to,
            'term_end_date_from' => $filters->term_end_date_from,
            'term_end_date_to' => $filters->term_end_date_to,
            'per_page' => $filters->per_page,
        ]));

        /** @var LengthAwarePaginator<int, NotificationDigest> $paginator */
        $paginator = $pageQuery->paginate($filters->per_page);

        $ids = $paginator->getCollection()->pluck('id')->all();

        if ($ids === []) {
            return $paginator;
        }

        $positions = array_flip($ids);

        $digests = NotificationDigest::query()
            ->with('organization:id,name')
            ->select([
                'id',
                'organization_id',
                'created_at',
                'data',
                'email_sent_at',
                'whatsapp_sent_at',
                'sms_sent_at',
                'viewed_at',
            ])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (NotificationDigest $digest): int => $positions[$digest->id] ?? 0)
            ->values();

        $paginator->setCollection($digests);

        return $paginator;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Organization\Data\OrganizationNotificationDigestFilterData;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Notification\NotificationDigestAccess;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;

/**
 * Admin detail of a persisted consolidado for an organization
 * (same payload shape as app-user, plus organization context).
 */
class GetOrganizationNotificationDigestDetailsService
{
    public function handle(
        Organization $organization,
        string $digestId,
        OrganizationNotificationDigestFilterData $filters,
    ): LengthAwarePaginator {
        $paginator = NotificationDigestAccess::details(
            $organization->id,
            $digestId,
            $filters->toArray(),
        );

        $viewStatus = $this->resolveViewStatus($organization->id, $digestId);

        $paginator->setCollection(
            $paginator->getCollection()->map(static function (mixed $item) use ($organization, $viewStatus): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $item['organization_id'] = $organization->id;
                $item['organization_name'] = $organization->name;
                $item['is_pending'] = false;
                $item['is_viewed'] = $viewStatus['is_viewed'];
                $item['viewed_at'] = $viewStatus['viewed_at'];

                return $item;
            }),
        );

        return $paginator;
    }

    /**
     * Same rule as the admin history list: the consolidado is viewed when any
     * organization member opened it (notification_digests.viewed_at).
     *
     * @return array{is_viewed: bool, viewed_at: string|null}
     */
    private function resolveViewStatus(string $organizationId, string $digestId): array
    {
        /** @var NotificationDigest|null $digest */
        $digest = NotificationDigest::query()
            ->whereOrganization($organizationId)
            ->whereKey($digestId)
            ->first();

        $viewedAt = $digest?->viewed_at;

        return [
            'is_viewed' => $viewedAt !== null,
            'viewed_at' => $viewedAt !== null
                ? DateFormatHelper::formatDateWithTime($viewedAt)
                : null,
        ];
    }
}

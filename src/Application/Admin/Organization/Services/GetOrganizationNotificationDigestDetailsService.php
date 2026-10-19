<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Organization\Data\OrganizationNotificationDigestFilterData;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\AppUser\Notification\Services\GetNotificationDigestDetailsService;
use Src\Domain\Organization\Models\Organization;

/**
 * Admin detail of a persisted consolidado for an organization
 * (same payload shape as app-user, plus organization context).
 */
class GetOrganizationNotificationDigestDetailsService
{
    public function __construct(
        private readonly GetNotificationDigestDetailsService $getNotificationDigestDetailsService,
    ) {}

    public function handle(
        Organization $organization,
        string $digestId,
        OrganizationNotificationDigestFilterData $filters,
    ): LengthAwarePaginator {
        $paginator = $this->getNotificationDigestDetailsService->handle(
            $organization->id,
            $digestId,
            NotificationDigestFilterData::from($filters->toArray()),
        );

        $paginator->setCollection(
            $paginator->getCollection()->map(static function (mixed $item) use ($organization): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $item['organization_id'] = $organization->id;
                $item['organization_name'] = $organization->name;
                $item['is_pending'] = false;

                return $item;
            }),
        );

        return $paginator;
    }
}

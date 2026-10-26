<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Src\Application\Admin\Organization\Data\OrganizationNotificationDigestFilterData;
use Src\Application\Shared\Notification\NotificationDigestAccess;
use Src\Application\Shared\Services\Notification\BuildNotificationDigestRowsService;
use Src\Application\Shared\Services\Notification\CollectPendingDigestNotificationsService;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;

/**
 * Read-only detail of the consolidado that would be sent for an organization
 * (digest package preview drill-down). Source-agnostic over ProcessAction rows.
 */
class GetOrganizationPendingDigestDetailsService
{
    public function __construct(
        private readonly CollectPendingDigestNotificationsService $collectPendingDigestNotificationsService,
        private readonly BuildNotificationDigestRowsService $buildNotificationDigestRowsService,
    ) {}

    public function handle(
        Organization $organization,
        OrganizationNotificationDigestFilterData $filters,
    ): LengthAwarePaginator {
        $digestFilters = NotificationDigestAccess::filters($filters->toArray());

        $notifications = $this->collectPendingDigestNotificationsService->handle($organization);
        $items = $this->buildNotificationDigestRowsService
            ->handle($notifications, $organization->id)
            ->values()
            ->all();

        $items = NotificationDigestAccess::filterRawItems($items, $digestFilters);
        $items = NotificationDigestAccess::sortRawItems($items);

        $totalActions = count($items);
        $currentPage = max(1, (int) \Illuminate\Pagination\Paginator::resolveCurrentPage());
        $perPage = max(1, $filters->per_page ?: 20);
        $pageItems = array_slice($items, ($currentPage - 1) * $perPage, $perPage);

        $ephemeralDigest = $this->makeEphemeralDigest($organization->id, $items);
        $enrichedPage = NotificationDigestAccess::formatItemsForPage(
            $ephemeralDigest,
            $pageItems,
            $organization->id,
        );

        $resource = array_merge(
            NotificationDigestAccess::buildMetadata($ephemeralDigest, $totalActions),
            [
                'id' => null,
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'is_pending' => true,
                'data' => $enrichedPage,
            ],
        );

        return $this->makePaginator([$resource], $totalActions, $perPage, $currentPage, $filters);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function makeEphemeralDigest(string $organizationId, array $items): NotificationDigest
    {
        $digest = new NotificationDigest([
            'organization_id' => $organizationId,
            'data' => $items,
        ]);
        $digest->forceFill([
            'id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $digest;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function makePaginator(
        array $items,
        int $totalActions,
        int $perPage,
        int $currentPage,
        OrganizationNotificationDigestFilterData $filters,
    ): LengthAwarePaginator {
        return new class($items, $totalActions, $perPage, $currentPage, ['path' => request()->url(), 'query' => array_merge(request()->query(), $filters->toArray())]) extends LengthAwarePaginator
        {
            public function toArray()
            {
                $array = parent::toArray();
                $actualItemsCount = count($this->items->first()['data'] ?? []);

                if ($actualItemsCount > 0) {
                    $array['from'] = ($this->currentPage() - 1) * $this->perPage() + 1;
                    $array['to'] = $array['from'] + $actualItemsCount - 1;
                } else {
                    $array['from'] = null;
                    $array['to'] = null;
                }

                return $array;
            }
        };
    }
}

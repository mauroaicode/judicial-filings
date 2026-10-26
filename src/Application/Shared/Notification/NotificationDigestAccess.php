<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notification;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\AppUser\Notification\Resources\NotificationDigestHistoryResource;
use Src\Application\AppUser\Notification\Resources\NotificationDigestResource;
use Src\Application\AppUser\Notification\Services\GetNotificationDigestDetailsService;
use Src\Domain\Notification\Models\NotificationDigest;

/**
 * Shared entry so Admin can reuse the app-user digest payload without importing that context.
 */
final class NotificationDigestAccess
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function filters(array $attributes): NotificationDigestFilterData
    {
        return NotificationDigestFilterData::from($attributes);
    }

    public static function history(NotificationDigest $digest): NotificationDigestHistoryResource
    {
        return NotificationDigestHistoryResource::fromModel($digest);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function details(string $organizationId, string $digestId, array $attributes): LengthAwarePaginator
    {
        return resolve(GetNotificationDigestDetailsService::class)->handle(
            $organizationId,
            $digestId,
            self::filters($attributes),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, array<string, mixed>>
     */
    public static function filterRawItems(array $data, NotificationDigestFilterData $filters): array
    {
        return NotificationDigestResource::filterRawItems($data, $filters);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function sortRawItems(array $items): array
    {
        return NotificationDigestResource::sortRawItems($items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pageItems
     * @return array<int, array<string, mixed>>
     */
    public static function formatItemsForPage(NotificationDigest $digest, array $pageItems, string $organizationId): array
    {
        return NotificationDigestResource::formatItemsForPage($digest, $pageItems, $organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildMetadata(NotificationDigest $digest, int $actionsCount): array
    {
        return NotificationDigestResource::buildMetadata($digest, $actionsCount);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Domain\Notification\Models\NotificationDigest;

readonly class NotificationDigestFinderService
{
    /**
     * Get paginated notification digests for the given organization and filters.
     * All filtering logic has been moved to NotificationDigestQueryBuilder to follow SRP.
     *
     * @param  NotificationDigestFilterData  $filters  The filtering criteria.
     * @param  string  $organizationId  The organization ID.
     * @param  int  $perPage  Number of items per page.
     */
    public function handle(NotificationDigestFilterData $filters, string $organizationId, int $perPage = 20): LengthAwarePaginator
    {
        if (! $filters->hasCriterialFilters()) {
            $lastDigestDate = NotificationDigest::query()
                ->where('organization_id', $organizationId)
                ->max('created_at');

            $activeDate = $lastDigestDate ? Date::parse($lastDigestDate)->format('Y-m-d') : now()->format('Y-m-d');

            $filters->created_at_from = $activeDate;
            $filters->created_at_to = $activeDate;
        }

        return NotificationDigest::query()
            ->whereOrganization($organizationId)
            ->filters($filters)
            ->latest()
            ->paginate($perPage);
    }
}

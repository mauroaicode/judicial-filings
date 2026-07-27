<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Services;

use Illuminate\Support\Collection;
use Src\Application\Admin\DigestPackage\Resources\DigestPackageOrganizationResource;
use Src\Application\Admin\DigestPackage\Resources\DigestPackagePreviewResource;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;

/**
 * Builds a read-only preview of the pending digest package.
 * No notifications are sent or marked; this is purely informational.
 */
class PreviewDigestPackageService
{
    public function handle(): DigestPackagePreviewResource
    {
        $organizations = $this->resolveOrganizationsWithPendingNotifications();

        $orgResources = $organizations->map(
            fn (Organization $org): DigestPackageOrganizationResource => $this->buildOrganizationResource($org)
        )->values()->all();

        $totalPending = array_sum(array_column(
            array_map(static fn (DigestPackageOrganizationResource $r): array => $r->toArray(), $orgResources),
            'pending_actions'
        ));

        return new DigestPackagePreviewResource(
            organizations_count: count($orgResources),
            total_pending_actions: $totalPending,
            auto_digest_enabled: (bool) config('judicial-sync.auto_digest_after_sync', true),
            organizations: $orgResources,
        );
    }

    /**
     * @return Collection<int, Organization>
     */
    private function resolveOrganizationsWithPendingNotifications(): Collection
    {
        return Organization::query()
            ->whereHas('notifications', fn (\Illuminate\Contracts\Database\Query\Builder $q) => $q->where('is_email_notified', false))
            ->with(['notificationChannels' => fn ($q) => $q->where('is_active', true)->orderBy('priority')])
            ->orderBy('name')
            ->get();
    }

    private function buildOrganizationResource(Organization $org): DigestPackageOrganizationResource
    {
        $pendingCount = $this->countPendingNotifications($org->id);
        $channels = $this->groupActiveChannels($org);

        return new DigestPackageOrganizationResource(
            organization_id: $org->id,
            organization_name: $org->name,
            pending_actions: $pendingCount,
            channels: $channels,
        );
    }

    private function countPendingNotifications(string $organizationId): int
    {
        return (int) OrganizationNotification::query()
            ->where('organization_id', $organizationId)
            ->where('is_email_notified', false)
            ->count();
    }

    /**
     * @return array<string, list<string>>
     */
    private function groupActiveChannels(Organization $org): array
    {
        /** @var array<string, list<string>> $grouped */
        $grouped = [];

        foreach ($org->notificationChannels as $channel) {
            $type = $channel->channel_type;
            $grouped[$type] ??= [];
            $grouped[$type][] = $channel->channel_value;
        }

        return $grouped;
    }
}

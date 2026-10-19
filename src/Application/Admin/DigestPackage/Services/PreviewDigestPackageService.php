<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Services;

use Illuminate\Support\Collection;
use Src\Application\Admin\DigestPackage\Resources\DigestPackageOrganizationResource;
use Src\Application\Admin\DigestPackage\Resources\DigestPackagePreviewResource;
use Src\Application\Shared\Services\Notification\BuildNotificationDigestRowsService;
use Src\Application\Shared\Services\Notification\CollectPendingDigestNotificationsService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Builds a read-only preview of the pending digest package.
 *
 * Uses the same eligibility pipeline as the consolidado send and the admin
 * pending-detail endpoint ({@see CollectPendingDigestNotificationsService}):
 * active processes, registration cutoff, and already-sent suppression.
 */
class PreviewDigestPackageService
{
    public function __construct(
        private readonly CollectPendingDigestNotificationsService $collectPendingDigestNotificationsService,
        private readonly BuildNotificationDigestRowsService $buildNotificationDigestRowsService,
    ) {}

    public function handle(): DigestPackagePreviewResource
    {
        $organizations = $this->resolveCandidateOrganizations();

        $orgResources = $organizations
            ->map(fn (Organization $org): DigestPackageOrganizationResource => $this->buildOrganizationResource($org))
            ->filter(static fn (DigestPackageOrganizationResource $resource): bool => $resource->pending_processes > 0)
            ->values()
            ->all();

        $totalProcesses = array_sum(array_map(
            static fn (DigestPackageOrganizationResource $r): int => $r->pending_processes,
            $orgResources,
        ));
        $totalActions = array_sum(array_map(
            static fn (DigestPackageOrganizationResource $r): int => $r->pending_actions,
            $orgResources,
        ));

        return new DigestPackagePreviewResource(
            consolidates_ready: count($orgResources),
            total_pending_processes: $totalProcesses,
            total_pending_actions: $totalActions,
            auto_digest_enabled: (bool) config('judicial-sync.auto_digest_after_sync', true),
            organizations: $orgResources,
        );
    }

    /**
     * @return Collection<int, Organization>
     */
    private function resolveCandidateOrganizations(): Collection
    {
        $morphClass = (new ProcessAction)->getMorphClass();

        return Organization::query()
            ->whereActive()
            ->whereHas(
                'notifications',
                fn (\Illuminate\Contracts\Database\Query\Builder $q) => $q
                    ->where('is_email_notified', false)
                    ->where('notifiable_type', $morphClass)
            )
            ->with(['notificationChannels' => fn ($q) => $q->where('is_active', true)->orderBy('priority')])
            ->orderBy('name')
            ->get();
    }

    private function buildOrganizationResource(Organization $org): DigestPackageOrganizationResource
    {
        $counts = $this->countEligiblePending($org);

        return new DigestPackageOrganizationResource(
            organization_id: $org->id,
            organization_name: $org->name,
            pending_processes: $counts['processes'],
            pending_actions: $counts['actions'],
            channels: $this->groupActiveChannels($org),
        );
    }

    /**
     * @return array{processes: int, actions: int}
     */
    private function countEligiblePending(Organization $organization): array
    {
        $notifications = $this->collectPendingDigestNotificationsService->handle($organization);

        if ($notifications->isEmpty()) {
            return ['processes' => 0, 'actions' => 0];
        }

        $rows = $this->buildNotificationDigestRowsService->handle($notifications, $organization->id);

        return [
            'processes' => $rows->pluck('process_number')->unique()->filter()->count(),
            'actions' => $rows->count(),
        ];
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

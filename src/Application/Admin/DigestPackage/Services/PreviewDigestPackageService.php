<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Services;

use Illuminate\Support\Collection;
use Src\Application\Admin\DigestPackage\Resources\DigestPackageOrganizationResource;
use Src\Application\Admin\DigestPackage\Resources\DigestPackagePreviewResource;
use Src\Application\Shared\Services\Notification\OrganizationNotificationRegistrationCutoffService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\QueryBuilders\OrganizationNotificationQueryBuilder;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Builds a read-only preview of the pending digest package.
 *
 * Aligns with {@see \Src\Application\Shared\Services\Notification\NotificationDigestService}:
 * only ProcessAction notifications that pass the registration cutoff are counted.
 * Other notifiable types (Process, Task, ProcessImportBatch, …) are excluded.
 */
class PreviewDigestPackageService
{
    public function __construct(
        private readonly OrganizationNotificationRegistrationCutoffService $registrationCutoffService,
    ) {}

    public function handle(): DigestPackagePreviewResource
    {
        $organizations = $this->resolveOrganizationsWithPendingActuacionNotifications();

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
    private function resolveOrganizationsWithPendingActuacionNotifications(): Collection
    {
        $morphClass = (new ProcessAction)->getMorphClass();

        return Organization::query()
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
        $counts = $this->countEligiblePending($org->id);

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
    private function countEligiblePending(string $organizationId): array
    {
        $query = $this->eligiblePendingQuery($organizationId);

        $actions = (clone $query)->count();

        $processes = (int) (clone $query)
            ->join('process_actions', 'organization_notifications.notifiable_id', '=', 'process_actions.id')
            ->distinct()
            ->count('process_actions.process_id');

        return [
            'processes' => $processes,
            'actions' => $actions,
        ];
    }

    private function eligiblePendingQuery(string $organizationId): OrganizationNotificationQueryBuilder
    {
        /** @var OrganizationNotificationQueryBuilder $query */
        $query = OrganizationNotification::query()
            ->where('organization_id', $organizationId)
            ->where('is_email_notified', false)
            ->forActiveOrganizationProcesses($organizationId);

        $this->registrationCutoffService->applyDigestPendingCutoff($query, $organizationId);

        return $query;
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

<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Services;

use Illuminate\Support\Facades\Log;
use Src\Application\Admin\DigestPackage\Resources\DigestPackageDiscardResource;
use Src\Application\Shared\Services\Notification\OrganizationNotificationRegistrationCutoffService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Removes one organization from the pending digest package by silencing its
 * digest-eligible ProcessAction notifications without sending email.
 *
 * Same eligibility rules as {@see PreviewDigestPackageService} /
 * {@see SendDigestPackageService} (ProcessAction + registration cutoff).
 */
class DiscardDigestPackageOrganizationService
{
    public function __construct(
        private readonly OrganizationNotificationRegistrationCutoffService $registrationCutoffService,
    ) {}

    public function handle(string $organizationId): DigestPackageDiscardResource
    {
        /** @var Organization|null $organization */
        $organization = Organization::query()->find($organizationId);

        if (! $organization instanceof Organization) {
            throw new NotFoundHttpException(__('digest_package.organization_not_found'));
        }

        $ids = $this->resolveEligiblePendingNotificationIds($organizationId);
        $discarded = $ids->count();

        if ($discarded > 0) {
            $this->silenceNotifications($ids->all());
        }

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('DiscardDigestPackageOrganizationService: pending consolidate discarded', [
                'organization_id' => $organizationId,
                'actions_discarded' => $discarded,
            ]);

        return new DigestPackageDiscardResource(
            organization_id: $organization->id,
            organization_name: $organization->name,
            actions_discarded: $discarded,
            message: $discarded > 0
                ? __('digest_package.discard_success', [
                    'organization' => $organization->name,
                    'count' => $discarded,
                ])
                : __('digest_package.discard_nothing_pending', [
                    'organization' => $organization->name,
                ]),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function resolveEligiblePendingNotificationIds(string $organizationId): \Illuminate\Support\Collection
    {
        $query = OrganizationNotification::query()
            ->where('organization_id', $organizationId)
            ->where('is_email_notified', false)
            ->forActiveOrganizationProcesses($organizationId);

        $this->registrationCutoffService->applyDigestPendingCutoff($query, $organizationId);

        return $query->pluck('id');
    }

    /**
     * @param  list<string>  $notificationIds
     */
    private function silenceNotifications(array $notificationIds): void
    {
        foreach (array_chunk($notificationIds, 500) as $chunk) {
            OrganizationNotification::query()
                ->whereIn('id', $chunk)
                ->update([
                    'is_email_notified' => true,
                    'email_notified_at' => now(),
                    'is_notified' => true,
                    'notified_at' => now(),
                    // No digest id: silenced without sending (same pattern as purge-stale).
                    'notification_digest_id' => null,
                ]);
        }
    }
}

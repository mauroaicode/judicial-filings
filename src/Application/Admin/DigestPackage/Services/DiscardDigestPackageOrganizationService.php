<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Services;

use Illuminate\Support\Facades\Log;
use Src\Application\Admin\DigestPackage\Resources\DigestPackageDiscardResource;
use Src\Application\Shared\Services\Notification\CollectPendingDigestNotificationsService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Removes one organization from the pending digest package by silencing its
 * digest-eligible ProcessAction notifications without sending email.
 *
 * Same eligibility rules as {@see PreviewDigestPackageService} /
 * {@see SendDigestPackageService}. Also silences already-sent duplicates that
 * would otherwise keep resurfacing in the raw pending queue.
 */
class DiscardDigestPackageOrganizationService
{
    public function __construct(
        private readonly CollectPendingDigestNotificationsService $collectPendingDigestNotificationsService,
    ) {}

    public function handle(string $organizationId): DigestPackageDiscardResource
    {
        /** @var Organization|null $organization */
        $organization = Organization::query()->find($organizationId);

        if (! $organization instanceof Organization) {
            throw new NotFoundHttpException(__('digest_package.organization_not_found'));
        }

        [$keep, $alreadySentDuplicates] = $this->collectPendingDigestNotificationsService
            ->collectPartitioned($organization);

        $toSilence = $keep->merge($alreadySentDuplicates);
        $discarded = $toSilence->count();

        if ($discarded > 0) {
            $this->silenceNotifications($toSilence->pluck('id')->all());
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

<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Services;

use Illuminate\Support\Facades\Log;
use Src\Application\Admin\DigestPackage\Resources\DigestPackageSendResource;
use Src\Application\Shared\Jobs\SendOrganizationDigestJob;
use Src\Domain\Organization\Models\Organization;

/**
 * Dispatches the pending digest package to all organizations that have
 * unnotified actuaciones.
 *
 * Reuses the same SendOrganizationDigestJob → NotificationDigestService
 * pipeline as the automatic post-cron dispatch, ensuring consistent
 * deduplication, channel delivery and markAsNotified behaviour.
 */
class SendDigestPackageService
{
    public function handle(): DigestPackageSendResource
    {
        $organizations = $this->resolveOrganizationsWithPendingNotifications();

        $this->dispatchDigestJobsForOrganizations($organizations);

        $count = $organizations->count();

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('SendDigestPackageService: manual digest package dispatched', [
                'organizations_queued' => $count,
            ]);

        return new DigestPackageSendResource(
            organizations_queued: $count,
            message: $count > 0
                ? __('digest_package.send_queued', ['count' => $count])
                : __('digest_package.send_nothing_pending'),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Organization>
     */
    private function resolveOrganizationsWithPendingNotifications(): \Illuminate\Support\Collection
    {
        return Organization::query()
            ->whereHas('notifications', fn (\Illuminate\Contracts\Database\Query\Builder $q) => $q->where('is_email_notified', false))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Organization>  $organizations
     */
    private function dispatchDigestJobsForOrganizations(\Illuminate\Support\Collection $organizations): void
    {
        foreach ($organizations as $organization) {
            dispatch(new SendOrganizationDigestJob($organization));
        }
    }
}

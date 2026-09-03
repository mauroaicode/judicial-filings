<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Src\Application\Admin\DigestPackage\Resources\DigestPackageSendResource;
use Src\Application\Shared\Jobs\SendOrganizationDigestJob;
use Src\Application\Shared\Services\Notification\OrganizationNotificationRegistrationCutoffService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Dispatches the pending digest package to organizations that have
 * digest-eligible unnotified actuaciones (ProcessAction + registration cutoff).
 *
 * Reuses the same SendOrganizationDigestJob → NotificationDigestService
 * pipeline as the automatic post-cron dispatch.
 */
class SendDigestPackageService
{
    public function __construct(
        private readonly OrganizationNotificationRegistrationCutoffService $registrationCutoffService,
    ) {}

    public function handle(): DigestPackageSendResource
    {
        $organizations = $this->resolveOrganizationsReadyToSend();

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
     * @return Collection<int, Organization>
     */
    private function resolveOrganizationsReadyToSend(): Collection
    {
        $morphClass = (new ProcessAction)->getMorphClass();

        $candidates = Organization::query()
            ->whereActive()
            ->whereHas(
                'notifications',
                fn (\Illuminate\Contracts\Database\Query\Builder $q) => $q
                    ->where('is_email_notified', false)
                    ->where('notifiable_type', $morphClass)
            )
            ->orderBy('name')
            ->get();

        return $candidates
            ->filter(fn (Organization $org): bool => $this->hasEligiblePendingActuaciones($org->id))
            ->values();
    }

    private function hasEligiblePendingActuaciones(string $organizationId): bool
    {
        $query = OrganizationNotification::query()
            ->where('organization_id', $organizationId)
            ->where('is_email_notified', false)
            ->forActiveOrganizationProcesses($organizationId);

        $this->registrationCutoffService->applyDigestPendingCutoff($query, $organizationId);

        return $query->exists();
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     */
    private function dispatchDigestJobsForOrganizations(Collection $organizations): void
    {
        foreach ($organizations as $organization) {
            dispatch(new SendOrganizationDigestJob($organization));
        }
    }
}

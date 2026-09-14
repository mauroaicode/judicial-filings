<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Database\Eloquent\Builder;
use Src\Application\Shared\Notifications\ManualRegistrationCompletedNotification;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Process\Models\Process;

/**
 * Email + in-app/WebSocket to the requesting lawyer (and org peers) after alta manual.
 */
readonly class NotifyAppUserManualRegistrationCompletedService
{
    public function handle(ManualRegistrationRequest $request): void
    {
        $request->loadMissing(['appUser', 'organization']);

        $process = Process::query()
            ->whereProcessNumber($request->process_number)
            ->whereHas('organizations', function (Builder $query) use ($request): void {
                $query->where('organizations.id', $request->organization_id);
            })
            ->first();

        $recipients = $this->resolveRecipients($request);

        foreach ($recipients as $appUser) {
            $appUser->notify(new ManualRegistrationCompletedNotification($request, $process));
        }
    }

    /**
     * @return list<AppUser>
     */
    private function resolveRecipients(ManualRegistrationRequest $request): array
    {
        $byId = [];

        if ($request->appUser instanceof AppUser) {
            $byId[$request->appUser->id] = $request->appUser;
        }

        $organization = $request->organization;
        if ($organization instanceof Organization) {
            $organization->loadMissing('appUsers');
            foreach ($organization->appUsers as $appUser) {
                $byId[$appUser->id] = $appUser;
            }
        }

        return array_values($byId);
    }
}

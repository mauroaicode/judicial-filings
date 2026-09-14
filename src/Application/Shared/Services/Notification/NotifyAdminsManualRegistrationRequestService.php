<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Notifications\ManualRegistrationRequestedNotification;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

/**
 * Sends database + broadcast notifications to all active admin Users.
 */
readonly class NotifyAdminsManualRegistrationRequestService
{
    public function handle(ManualRegistrationRequest $request): void
    {
        $admins = User::query()
            ->where('state', UserStatus::ACTIVE)
            ->whereHas('roles', function (\Illuminate\Contracts\Database\Query\Builder $query): void {
                $query->where('name', 'admin')->where('guard_name', 'admin');
            })
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new ManualRegistrationRequestedNotification($request));
    }
}

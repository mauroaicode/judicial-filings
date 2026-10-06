<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Notifications\ProcessExportFinishedNotification;
use Src\Domain\Process\Models\ProcessExport;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

/**
 * Sends database + broadcast notification when a process export finishes.
 * Prefers the requesting admin; falls back to all active admins.
 */
readonly class NotifyAdminProcessExportFinishedService
{
    public function handle(ProcessExport $export): void
    {
        $export->loadMissing('requestedByUser');

        $requester = $export->requestedByUser;
        if ($requester instanceof User && $requester->state === UserStatus::ACTIVE) {
            $requester->notify(new ProcessExportFinishedNotification($export));

            return;
        }

        $admins = User::query()
            ->where('state', UserStatus::ACTIVE)
            ->whereHas('roles', function (\Illuminate\Contracts\Database\Query\Builder $query): void {
                $query->where('name', 'admin')->where('guard_name', 'admin');
            })
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new ProcessExportFinishedNotification($export));
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\DTOs\TaskDueDateReminderAlert;
use Src\Application\Shared\Notifications\TaskDueDateReminderInternalNotification;
use Src\Domain\Organization\Models\Organization;

class InternalTaskDueDateReminderChannelDriver
{
    public function send(TaskDueDateReminderAlert $alert, string $organizationId): void
    {
        $organization = Organization::query()
            ->with('appUsers')
            ->find($organizationId);

        if ($organization === null || $organization->appUsers->isEmpty()) {
            Log::channel(config('tasks.log_channel', 'stack'))->info('Task due-date internal reminder skipped (no app users)', [
                'organization_id' => $organizationId,
                'task_id' => $alert->task->id,
            ]);

            return;
        }

        Notification::send(
            $organization->appUsers,
            new TaskDueDateReminderInternalNotification($alert),
        );
    }
}

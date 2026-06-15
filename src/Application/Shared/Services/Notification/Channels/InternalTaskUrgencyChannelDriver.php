<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\DTOs\TaskUrgencyAlert;
use Src\Application\Shared\Notifications\TaskUrgencyInternalNotification;
use Src\Domain\Organization\Models\Organization;

class InternalTaskUrgencyChannelDriver
{
    public function send(TaskUrgencyAlert $alert, string $organizationId): void
    {
        $organization = Organization::query()
            ->with('appUsers')
            ->find($organizationId);

        if ($organization === null || $organization->appUsers->isEmpty()) {
            Log::channel(config('tasks.log_channel', 'stack'))->info('Task urgency internal notification skipped (no app users)', [
                'organization_id' => $organizationId,
                'task_id' => $alert->task->id,
            ]);

            return;
        }

        Notification::send(
            $organization->appUsers,
            new TaskUrgencyInternalNotification($alert),
        );

        Log::channel(config('tasks.log_channel', 'stack'))->info('Task urgency internal notification dispatched', [
            'organization_id' => $organizationId,
            'task_id' => $alert->task->id,
            'user_count' => $organization->appUsers->count(),
            'urgency_level' => $alert->urgencyLevel->value,
        ]);
    }
}

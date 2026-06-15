<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Task;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\DTOs\TaskDueDateReminderAlert;
use Src\Application\Shared\Helpers\TaskDueDateReminderHelper;
use Src\Application\Shared\Services\Notification\TaskDueDateReminderNotificationService;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Models\Task;

class ProcessPendingTaskDueDateRemindersService
{
    public function __construct(
        private readonly TaskDueDateReminderNotificationService $notificationService,
    ) {}

    /**
     * @return array{processed: int, notified: int}
     */
    public function handle(?string $organizationId = null): array
    {
        $processed = 0;
        $notified = 0;

        Task::query()
            ->with(['process', 'organization.appUsers'])
            ->whereStatus(TaskStatus::PENDING)
            ->whereNotNull('due_date')
            ->when($organizationId, fn ($q) => $q->whereOrganization($organizationId))
            ->orderBy('due_date')
            ->chunkById(100, function ($tasks) use (&$processed, &$notified): void {
                foreach ($tasks as $task) {
                    $processed++;

                    $daysRemaining = TaskDueDateReminderHelper::resolveNotifiableDaysRemaining($task);

                    if ($daysRemaining === null) {
                        continue;
                    }

                    $alert = new TaskDueDateReminderAlert(
                        task: $task,
                        daysRemaining: $daysRemaining,
                        taskUrl: TaskDueDateReminderHelper::buildTaskFrontendUrl($task->id),
                    );

                    if ($this->notificationService->notify($alert)) {
                        $task->update(['last_due_reminder_sent_on' => today()]);
                        $notified++;
                    }
                }
            });

        Log::channel(config('tasks.log_channel', 'stack'))->info('ProcessPendingTaskDueDateRemindersService completed', [
            'organization_id' => $organizationId,
            'processed' => $processed,
            'notified' => $notified,
        ]);

        return ['processed' => $processed, 'notified' => $notified];
    }
}

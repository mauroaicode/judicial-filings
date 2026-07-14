<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Task;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\DTOs\TaskUrgencyAlert;
use Src\Application\Shared\Helpers\TaskUrgencyHelper;
use Src\Application\Shared\Services\Notification\TaskUrgencyNotificationService;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskUrgencyLevel;
use Src\Domain\Task\Models\Task;

class ProcessPendingTaskUrgencyAlertsService
{
    public function __construct(
        private readonly TaskUrgencyNotificationService $notificationService,
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
            ->when($organizationId, fn ($q): \Src\Domain\Task\QueryBuilders\TaskQueryBuilder => $q->whereOrganization($organizationId))->oldest()
            ->chunkById(100, function ($tasks) use (&$processed, &$notified): void {
                foreach ($tasks as $task) {
                    $processed++;

                    $urgencyLevel = TaskUrgencyHelper::resolveNotifiableLevel($task);

                    if (! $urgencyLevel instanceof TaskUrgencyLevel) {
                        continue;
                    }

                    $alert = new TaskUrgencyAlert(
                        task: $task,
                        urgencyLevel: $urgencyLevel,
                        daysElapsed: TaskUrgencyHelper::daysOverdue($task->due_date),
                        taskUrl: TaskUrgencyHelper::buildTaskFrontendUrl($task->id),
                    );

                    if ($this->notificationService->notify($alert)) {
                        $task->update(['last_notified_urgency_level' => $urgencyLevel->value]);
                        $notified++;
                    }
                }
            });

        Log::channel(config('tasks.log_channel', 'stack'))->info('ProcessPendingTaskUrgencyAlertsService completed', [
            'organization_id' => $organizationId,
            'processed' => $processed,
            'notified' => $notified,
        ]);

        return ['processed' => $processed, 'notified' => $notified];
    }
}

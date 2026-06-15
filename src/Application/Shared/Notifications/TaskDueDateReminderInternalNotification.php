<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Application\Shared\DTOs\TaskDueDateReminderAlert;

class TaskDueDateReminderInternalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TaskDueDateReminderAlert $alert,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        $queue = config('tasks.queues.internal', 'notifications');

        return [
            'database' => $queue,
            'broadcast' => $queue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->getData();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->getData()))
            ->onQueue(config('tasks.queues.internal', 'notifications'));
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(): array
    {
        $days = $this->alert->daysRemaining;
        $processNumber = $this->alert->processNumber();

        return [
            'title' => $days === 0
                ? __('task.due_reminder_internal_title_today')
                : __('task.due_reminder_internal_title', ['days' => $days]),
            'description' => $days === 0
                ? __('task.due_reminder_internal_description_today', [
                    'title' => $this->alert->task->title,
                    'process' => $processNumber ?? __('task.no_process_associated'),
                ])
                : __('task.due_reminder_internal_description', [
                    'title' => $this->alert->task->title,
                    'process' => $processNumber ?? __('task.no_process_associated'),
                    'days' => $days,
                ]),
            'type' => 'task-due-reminder',
            'task_id' => $this->alert->task->id,
            'task_title' => $this->alert->task->title,
            'process_id' => $this->alert->task->process_id,
            'process_number' => $processNumber,
            'days_remaining' => $days,
            'due_date' => $this->alert->task->due_date?->toDateString(),
            'url' => $this->alert->taskUrl,
        ];
    }

    public function broadcastType(): string
    {
        return 'TaskDueDateReminder';
    }
}

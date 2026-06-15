<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Application\Shared\DTOs\TaskUrgencyAlert;
use Src\Domain\Task\Enums\TaskUrgencyLevel;

class TaskUrgencyInternalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TaskUrgencyAlert $alert,
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
        $level = $this->alert->urgencyLevel;
        $processNumber = $this->alert->processNumber();

        return [
            'title' => $this->titleForLevel($level),
            'description' => $this->descriptionForLevel($level, $processNumber),
            'type' => 'task-urgency',
            'task_id' => $this->alert->task->id,
            'task_title' => $this->alert->task->title,
            'process_id' => $this->alert->task->process_id,
            'process_number' => $processNumber,
            'urgency_level' => $level->value,
            'urgency_label' => $level->getLabel(),
            'days_elapsed' => $this->alert->daysElapsed,
            'url' => $this->alert->taskUrl,
        ];
    }

    private function titleForLevel(TaskUrgencyLevel $level): string
    {
        return match ($level) {
            TaskUrgencyLevel::ALERT_1 => __('task.urgency_internal_title_alert_1'),
            TaskUrgencyLevel::ALERT_2 => __('task.urgency_internal_title_alert_2'),
            TaskUrgencyLevel::CRITICAL => __('task.urgency_internal_title_critical'),
            default => __('task.urgency_internal_title_default'),
        };
    }

    private function descriptionForLevel(TaskUrgencyLevel $level, ?string $processNumber): string
    {
        $processLabel = $processNumber ?? __('task.no_process_associated');

        return match ($level) {
            TaskUrgencyLevel::ALERT_1 => __('task.urgency_internal_description_alert_1', [
                'title' => $this->alert->task->title,
                'process' => $processLabel,
                'days' => $this->alert->daysElapsed,
            ]),
            TaskUrgencyLevel::ALERT_2 => __('task.urgency_internal_description_alert_2', [
                'title' => $this->alert->task->title,
                'process' => $processLabel,
                'days' => $this->alert->daysElapsed,
            ]),
            TaskUrgencyLevel::CRITICAL => __('task.urgency_internal_description_critical', [
                'title' => $this->alert->task->title,
                'process' => $processLabel,
                'days' => $this->alert->daysElapsed,
            ]),
            default => __('task.urgency_internal_description_default', [
                'title' => $this->alert->task->title,
                'process' => $processLabel,
                'days' => $this->alert->daysElapsed,
            ]),
        };
    }

    public function broadcastType(): string
    {
        return 'TaskUrgencyAlert';
    }
}

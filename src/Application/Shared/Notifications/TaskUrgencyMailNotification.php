<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\Application\Shared\DTOs\TaskUrgencyAlert;
use Src\Domain\Task\Enums\TaskUrgencyLevel;

class TaskUrgencyMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TaskUrgencyAlert $alert,
    ) {
        $this->onQueue(config('tasks.queues.email', 'notifications-email'));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $level = $this->alert->urgencyLevel;

        return (new MailMessage)
            ->subject($this->subjectForLevel($level))
            ->view('emails.task-urgency-alert', [
                'alert' => $this->alert,
                'headline' => $this->headlineForLevel($level),
                'body' => $this->bodyForLevel($level),
                'badgeLabel' => $this->badgeLabelForLevel($level),
                'accentColor' => $this->accentColorForLevel($level),
            ]);
    }

    private function badgeLabelForLevel(TaskUrgencyLevel $level): string
    {
        return match ($level) {
            TaskUrgencyLevel::ALERT_1 => __('task.urgency_email_badge_alert_1'),
            TaskUrgencyLevel::ALERT_2 => __('task.urgency_email_badge_alert_2'),
            TaskUrgencyLevel::CRITICAL => __('task.urgency_email_badge_critical'),
            default => __('task.urgency_email_badge_default'),
        };
    }

    private function subjectForLevel(TaskUrgencyLevel $level): string
    {
        return match ($level) {
            TaskUrgencyLevel::ALERT_1 => __('task.urgency_email_subject_alert_1', ['title' => $this->alert->task->title]),
            TaskUrgencyLevel::ALERT_2 => __('task.urgency_email_subject_alert_2', ['title' => $this->alert->task->title]),
            TaskUrgencyLevel::CRITICAL => __('task.urgency_email_subject_critical', ['title' => $this->alert->task->title]),
            default => __('task.urgency_email_subject_default', ['title' => $this->alert->task->title]),
        };
    }

    private function headlineForLevel(TaskUrgencyLevel $level): string
    {
        return match ($level) {
            TaskUrgencyLevel::ALERT_1 => __('task.urgency_email_headline_alert_1'),
            TaskUrgencyLevel::ALERT_2 => __('task.urgency_email_headline_alert_2'),
            TaskUrgencyLevel::CRITICAL => __('task.urgency_email_headline_critical'),
            default => __('task.urgency_email_headline_default'),
        };
    }

    private function bodyForLevel(TaskUrgencyLevel $level): string
    {
        return match ($level) {
            TaskUrgencyLevel::ALERT_1 => __('task.urgency_email_body_alert_1', [
                'days' => $this->alert->daysElapsed,
                'title' => $this->alert->task->title,
            ]),
            TaskUrgencyLevel::ALERT_2 => __('task.urgency_email_body_alert_2', [
                'days' => $this->alert->daysElapsed,
                'title' => $this->alert->task->title,
            ]),
            TaskUrgencyLevel::CRITICAL => __('task.urgency_email_body_critical', [
                'days' => $this->alert->daysElapsed,
                'title' => $this->alert->task->title,
            ]),
            default => __('task.urgency_email_body_default', [
                'days' => $this->alert->daysElapsed,
                'title' => $this->alert->task->title,
            ]),
        };
    }

    private function accentColorForLevel(TaskUrgencyLevel $level): string
    {
        return match ($level) {
            TaskUrgencyLevel::ALERT_1 => '#F59E0B',
            TaskUrgencyLevel::ALERT_2 => '#F97316',
            TaskUrgencyLevel::CRITICAL => '#EF4444',
            default => '#4B2A7D',
        };
    }
}

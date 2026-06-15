<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\Application\Shared\DTOs\TaskDueDateReminderAlert;

class TaskDueDateReminderMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TaskDueDateReminderAlert $alert,
    ) {
        $this->onQueue(config('tasks.queues.email', 'emails'));
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
        $days = $this->alert->daysRemaining;

        return (new MailMessage)
            ->subject($this->subjectForDays($days))
            ->view('emails.task-due-date-reminder', [
                'alert' => $this->alert,
                'headline' => $this->headlineForDays($days),
                'body' => $this->bodyForDays($days),
                'accentColor' => $days === 0 ? '#EF4444' : '#4B2A7D',
            ]);
    }

    private function subjectForDays(int $days): string
    {
        if ($days === 0) {
            return __('task.due_reminder_email_subject_today', ['title' => $this->alert->task->title]);
        }

        return __('task.due_reminder_email_subject', [
            'title' => $this->alert->task->title,
            'days' => $days,
        ]);
    }

    private function headlineForDays(int $days): string
    {
        if ($days === 0) {
            return __('task.due_reminder_email_headline_today');
        }

        return __('task.due_reminder_email_headline', ['days' => $days]);
    }

    private function bodyForDays(int $days): string
    {
        if ($days === 0) {
            return __('task.due_reminder_email_body_today', ['title' => $this->alert->task->title]);
        }

        return __('task.due_reminder_email_body', [
            'title' => $this->alert->task->title,
            'days' => $days,
        ]);
    }
}

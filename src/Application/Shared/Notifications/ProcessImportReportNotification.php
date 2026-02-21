<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\Application\Shared\DTOs\ProcessImportReport;

class ProcessImportReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProcessImportReport $report
    ) {
        $this->onQueue('emails_import_report');
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
        return (new MailMessage)
            ->subject(__('process.import_report_subject'))
            ->view('emails.process-import-report', [
                'report' => $this->report,
            ]);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Src\Application\Shared\DTOs\ProcessImportReport;

class ProcessImportReportMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ProcessImportReport $report
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('process.import_report_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.process-import-report',
        );
    }
}

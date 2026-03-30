<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ConsolidatedJudicialActionsMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  Collection  $data  This collection contains structured data for each row in the table
     */
    public function __construct(
        public readonly Collection $data,
        public readonly string $organizationName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('process.consolidated_notifications_subject', ['name' => $this->organizationName, 'date' => now()->format('Y-m-d')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consolidated-judicial-actions',
        );
    }
}

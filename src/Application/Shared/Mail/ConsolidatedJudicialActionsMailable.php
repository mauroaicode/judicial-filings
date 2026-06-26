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

    /** @var array<string, mixed> */
    public readonly array $emailViewData;

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     */
    public function __construct(
        Collection $data,
        public readonly string $organizationName,
        public readonly string $digestId,
        ?ConsolidatedDigestEmailPresenter $presenter = null,
    ) {
        $presenter ??= new ConsolidatedDigestEmailPresenter;

        $this->emailViewData = array_merge(
            $presenter->present($data, $digestId),
            ['data' => $data],
        );
    }

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
            with: $this->emailViewData,
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Src\Application\Shared\Helpers\ProcessNumberFormatHelper;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

class ProcessActionRemovedFromJudicialBranchMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ProcessAction $action,
        public readonly Process $process,
        public readonly ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('process.action_removed_from_judicial_branch_subject', [
                'number' => ProcessNumberFormatHelper::display($this->process->process_number),
            ]),
        );
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.frontend_url', 'http://localhost:4200'), '/');

        return new Content(
            view: 'emails.process-action-removed-from-judicial-branch',
            with: [
                'ctaUrl' => $base.'/gestion-procesos/'.$this->process->id,
            ],
        );
    }
}

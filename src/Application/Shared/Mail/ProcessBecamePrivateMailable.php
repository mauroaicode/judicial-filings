<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Src\Domain\Process\Models\Process;

/**
 * Correo que se envía a las organizaciones cuando un proceso pasa de público
 * a privado en Rama Judicial y el sistema intenta migrarlo a SAMAI.
 */
class ProcessBecamePrivateMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Process $process,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('process.became_private_subject', [
                'number' => $this->process->process_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.process-became-private',
        );
    }
}

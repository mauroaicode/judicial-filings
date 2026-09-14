<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Process\Models\Process;

/**
 * Correo al abogado cuando el asesor completa el alta manual del radicado.
 */
class ManualRegistrationCompletedMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ManualRegistrationRequest $registrationRequest,
        public readonly ?Process $process = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('process.manual_registration_completed_subject', [
                'number' => $this->registrationRequest->process_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.manual-registration-completed',
            with: [
                'registrationRequest' => $this->registrationRequest,
                'process' => $this->process,
                'processUrl' => $this->processUrl(),
            ],
        );
    }

    private function processUrl(): string
    {
        $base = rtrim((string) config('app.frontend_url', 'http://localhost:4200'), '/');

        if ($this->process instanceof Process) {
            return $base.'/gestion-procesos/'.$this->process->id;
        }

        return $base.'/gestion-procesos';
    }
}

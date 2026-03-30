<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

class JudicialActionDetectedMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $notificationType  'actuacion' | 'actuacion_alerta'
     */
    public function __construct(
        public readonly ProcessAction $action,
        public readonly Process $process,
        public readonly string $organizationId,
        public readonly string $notificationType
    ) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->notificationType === 'actuacion_alerta'
            ? 'process.alert_detected_subject'
            : 'process.action_detected_subject';

        return new Envelope(
            subject: __($subjectKey, ['number' => $this->process->process_number]),
        );
    }

    public function content(): Content
    {
        $matchedKeywords = null;

        if ($this->notificationType === 'actuacion_alerta') {
            $matchedKeywords = ProcessActionAlertHighlight::query()
                ->where('process_action_id', $this->action->id)
                ->where('organization_id', $this->organizationId)
                ->pluck('detected_text')
                ->unique()
                ->implode(', ');
        }

        return new Content(
            view: 'emails.judicial-action-detected',
            with: [
                'matchedKeywords' => $matchedKeywords,
            ]
        );
    }
}

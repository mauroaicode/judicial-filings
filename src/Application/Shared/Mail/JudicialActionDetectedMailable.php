<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Src\Application\Admin\Process\Services\NotifyProcessActionDocumentUploadedService;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

class JudicialActionDetectedMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $notificationType  'actuacion' | 'actuacion_alerta' | 'actuacion_documento'
     */
    public function __construct(
        public readonly ProcessAction $action,
        public readonly Process $process,
        public readonly string $organizationId,
        public readonly string $notificationType,
        public readonly ?int $mediaId = null,
        public readonly ?string $originalFilename = null,
    ) {}

    public function envelope(): Envelope
    {
        $subjectKey = match ($this->notificationType) {
            'actuacion_alerta' => 'process.alert_detected_subject',
            NotifyProcessActionDocumentUploadedService::NOTIFICATION_TYPE => 'process.document_uploaded_subject',
            default => 'process.action_detected_subject',
        };

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
                'ctaUrl' => $this->ctaUrl(),
                'ctaLabel' => $this->ctaLabel(),
                'originalFilename' => $this->originalFilename,
            ]
        );
    }

    private function ctaUrl(): string
    {
        $base = rtrim((string) config('app.frontend_url', 'http://localhost:4200'), '/');
        $path = $base.'/gestion-procesos/'.$this->process->id;

        if ($this->notificationType !== NotifyProcessActionDocumentUploadedService::NOTIFICATION_TYPE) {
            return $path;
        }

        $query = array_filter([
            'section' => 'documents',
            'mediaId' => $this->mediaId,
            'filename' => $this->originalFilename,
            'actionId' => $this->action->id,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return $path.'?'.http_build_query($query);
    }

    private function ctaLabel(): string
    {
        return $this->notificationType === NotifyProcessActionDocumentUploadedService::NOTIFICATION_TYPE
            ? __('process.view_document')
            : __('process.view_process');
    }
}

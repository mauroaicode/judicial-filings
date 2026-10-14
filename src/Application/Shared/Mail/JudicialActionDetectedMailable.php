<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Src\Application\Admin\Process\Services\NotifyProcessActionDocumentUploadedService;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;
use Src\Domain\Process\Services\GroupProcessActionsService;

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

        $isDocument = $this->notificationType === NotifyProcessActionDocumentUploadedService::NOTIFICATION_TYPE;

        return new Content(
            view: 'emails.judicial-action-detected',
            with: [
                'matchedKeywords' => $matchedKeywords,
                'ctaUrl' => $this->ctaUrl(),
                'ctaLabel' => $this->ctaLabel(),
                'originalFilename' => $this->originalFilename,
                'plaintiffs' => $isDocument ? $this->partyNames('Demandante') : null,
                'defendants' => $isDocument ? $this->partyNames('Demandado') : null,
                'pairedAction' => $isDocument ? $this->resolvePairedAction() : null,
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

    private function partyNames(string $subjectType): string
    {
        $names = $this->process->subjects()
            ->where('subject_type', $subjectType)
            ->pluck('name_or_business_name')
            ->unique()
            ->sort()
            ->values()
            ->map(fn (?string $name): ?string => StrParseHelper::toTitleCase($name))
            ->filter()
            ->implode(', ');

        return $names !== '' ? $names : '---';
    }

    /**
     * Resolve fijación ↔ auto pair for the document email actuación block.
     *
     * @return array{fijacion: string, auto: string}|null
     */
    private function resolvePairedAction(): ?array
    {
        $actions = ProcessAction::query()
            ->where('process_id', $this->process->id)
            ->orderByDesc('cons_action')
            ->limit(80)
            ->get(['id', 'action', 'annotation', 'cons_action', 'registration_date']);

        if ($actions->count() < 2) {
            return null;
        }

        /** @var Collection<int, array<string, mixed>> $items */
        $items = $actions->map(fn (ProcessAction $candidate): array => [
            'id' => $candidate->id,
            'process_action_id' => $candidate->id,
            'process_number' => $this->process->process_number,
            'action' => $candidate->action,
            'action_text' => $candidate->action,
            'annotation' => $candidate->annotation,
            'cons_action' => $candidate->cons_action,
            'registration_date' => $candidate->registration_date,
        ]);

        $grouped = resolve(GroupProcessActionsService::class)->handle($items);
        $current = $grouped->firstWhere('process_action_id', $this->action->id)
            ?? $grouped->firstWhere('id', $this->action->id);

        if (! is_array($current)) {
            return null;
        }

        $fijacionId = $current['fijacion_action_id'] ?? null;
        $autoId = $current['notified_action_id'] ?? null;

        if ($autoId) {
            $auto = $actions->firstWhere('id', $autoId);

            return $auto instanceof ProcessAction
                ? [
                    'fijacion' => (string) $this->action->action,
                    'auto' => (string) $auto->action,
                ]
                : null;
        }

        if ($fijacionId) {
            $fijacion = $actions->firstWhere('id', $fijacionId);

            return $fijacion instanceof ProcessAction
                ? [
                    'fijacion' => (string) $fijacion->action,
                    'auto' => (string) $this->action->action,
                ]
                : null;
        }

        return null;
    }
}

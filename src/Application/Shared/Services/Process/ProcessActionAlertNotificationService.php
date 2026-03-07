<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Str;
use Src\Application\Shared\Contracts\Alert\AnnotationAlertDetectionInterface;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Events\JudicialActionDetected;
use Src\Domain\Process\Models\AlertActionKeyword;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

readonly class ProcessActionAlertNotificationService
{
    public function __construct(
        private AnnotationAlertDetectionInterface $alertDetection
    ) {}

    public function handle(ProcessAction $action, Process $process): void
    {
        $annotation = $action->annotation ?? '';
        $actionText = $action->action ?? '';
        $anno = trim($annotation);
        $act = trim($actionText);
        // Buscar palabras/frases en anotación Y en actuación (texto concatenado).
        $searchText = $anno === '' && $act === '' ? '' : trim($anno.' '.$act);
        $spans = $this->alertDetection->getDetectedAlertSpans($searchText);

        // Límite para saber si el span está en anotación o actuación al guardar en ProcessActionAlertHighlight.
        $annotationBoundary = mb_strlen($anno) + ($anno !== '' && $act !== '' ? 1 : 0);
        $this->saveHighlights($action, $spans, $annotationBoundary);
        $this->createNotificationsAndDispatch($process, $action, $spans);
    }

    /**
     * Guarda en ProcessActionAlertHighlight la posición (start, end) y el origen (annotation|action|both)
     * de cada fragmento detectado en el texto concatenado anotación + actuación.
     *
     * @param  array<int, array{start: int, end: int, text: string}>  $spans
     */
    private function saveHighlights(ProcessAction $action, array $spans, int $annotationBoundary): void
    {
        $keywordIds = [];
        foreach ($spans as $span) {
            $source = $this->computeSource($span['start'], $span['end'], $annotationBoundary);
            ProcessActionAlertHighlight::query()->create([
                'process_action_id' => $action->id,
                'start' => $span['start'],
                'end' => $span['end'],
                'detected_text' => $span['text'],
                'source' => $source,
            ]);
            $keyword = AlertActionKeyword::matchFragment($span['text']);
            if ($keyword instanceof AlertActionKeyword) {
                $keywordIds[$keyword->id] = [];
            }
        }

        if ($keywordIds !== []) {
            $action->alertActionKeywords()->syncWithoutDetaching(array_keys($keywordIds));
        }
    }

    /**
     * Donde se encontró el fragmento en el texto concatenado (anotación + actuación).
     */
    private function computeSource(int $start, int $end, int $annotationBoundary): string
    {
        if ($annotationBoundary <= 0) {
            return 'action';
        }

        if ($end <= $annotationBoundary) {
            return 'annotation';
        }

        if ($start >= $annotationBoundary) {
            return 'action';
        }

        return 'both';
    }

    /**
     * @param  array<int, array{start: int, end: int, text: string}>  $spans
     */
    private function createNotificationsAndDispatch(Process $process, ProcessAction $action, array $spans): void
    {
        $organizations = $process->organizations()->wherePivot('is_active', true)->get();

        foreach ($organizations as $organization) {
            $this->createNotificationAndDispatch($action, $organization->id, 'actuacion');

            if ($spans !== []) {
                $this->createNotificationAndDispatch($action, $organization->id, 'actuacion_alerta');
            }
        }
    }

    private function createNotificationAndDispatch(ProcessAction $action, string $organizationId, string $notificationType): void
    {
        $notification = OrganizationNotification::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'notifiable_id' => $action->id,
                'notifiable_type' => $action->getMorphClass(),
                'notification_type' => $notificationType,
            ],
            [
                'id' => (string) Str::uuid(),
                'is_viewed' => false,
                'is_notified' => false,
            ]
        );

        dispatch(SendOrganizationNotificationJob::fromNotification($notification));

        event(new JudicialActionDetected($action, $organizationId, $notificationType));
    }
}

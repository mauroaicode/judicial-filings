<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Src\Application\Shared\Contracts\Alert\AnnotationAlertDetectionInterface;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Domain\Notification\Models\OrganizationNotification;
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
        $spans = $this->alertDetection->getDetectedAlertSpans($annotation);

        $this->saveHighlights($action, $spans);
        $this->createNotificationsAndDispatch($process, $action, $spans);
    }

    /**
     * @param  array<int, array{start: int, end: int, text: string}>  $spans
     */
    private function saveHighlights(ProcessAction $action, array $spans): void
    {
        foreach ($spans as $span) {
            ProcessActionAlertHighlight::query()->create([
                'process_action_id' => $action->id,
                'start' => $span['start'],
                'end' => $span['end'],
                'detected_text' => $span['text'],
            ]);
        }
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
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'is_viewed' => false,
                'is_notified' => false,
            ]
        );

        dispatch(SendOrganizationNotificationJob::fromNotification($notification));
    }
}

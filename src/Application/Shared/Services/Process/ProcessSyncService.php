<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Alert\AnnotationAlertDetectionInterface;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Traits\MapsJudicialActuacionTrait;
use Src\Application\Shared\Traits\MapsJudicialSujetoTrait;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessSubject;

class ProcessSyncService
{
    use MapsJudicialActuacionTrait;
    use MapsJudicialSujetoTrait;

    public function __construct(
        private readonly JudicialBranchConsultService $judicialService,
        private readonly AnnotationAlertDetectionInterface $alertDetection
    ) {}

    public function handle(Process $process): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        $apiProcessId = (int) $process->process_id;

        $actionsResult = $this->judicialService->fetchActionByProcess($apiProcessId);
        if (! $actionsResult->isSuccessful) {
            Log::channel($channel)->error('ProcessSyncService: failed to fetch actuaciones', [
                'process_id' => $process->id,
            ]);

            return;
        }

        $subjectsResult = $this->judicialService->fetchSubjectsByProcess($apiProcessId);
        if (! $subjectsResult->isSuccessful) {
            Log::channel($channel)->error('ProcessSyncService: failed to fetch sujetos', [
                'process_id' => $process->id,
            ]);

            return;
        }

        $this->syncActuaciones($process, $actionsResult->data);
        $this->syncSujetos($process, $subjectsResult->data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiActuaciones
     */
    private function syncActuaciones(Process $process, array $apiActuaciones): void
    {
        $existingIds = ProcessAction::query()
            ->whereProcess($process->id)
            ->pluck('action_registration_id')
            ->flip()
            ->all();

        foreach ($apiActuaciones as $apiActuacion) {
            $idReg = (int) ($apiActuacion['idRegActuacion'] ?? 0);
            if (isset($existingIds[$idReg])) {
                continue;
            }

            $attributes = $this->mapApiActuacionToAttributes($apiActuacion);
            $attributes['process_id'] = $process->id;

            $action = ProcessAction::query()->create($attributes);

            $annotation = $action->annotation ?? '';
            $isAlert = $this->alertDetection->containsAlertKeywords($annotation);

            $this->createNotificationsAndDispatch($process, $action, 'actuacion');

            if ($isAlert) {
                $this->createNotificationsAndDispatch($process, $action, 'actuacion_alerta');
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiSujetos
     */
    private function syncSujetos(Process $process, array $apiSujetos): void
    {
        $existingIds = ProcessSubject::query()
            ->whereProcess($process->id)
            ->pluck('subject_registration_id')
            ->flip()
            ->all();

        foreach ($apiSujetos as $apiSujeto) {
            $idReg = (int) ($apiSujeto['idRegSujeto'] ?? 0);
            if (isset($existingIds[$idReg])) {
                continue;
            }

            $attributes = $this->mapApiSujetoToAttributes($apiSujeto);
            $attributes['process_id'] = $process->id;

            $subject = ProcessSubject::query()->create($attributes);

            $this->createNotificationsAndDispatch($process, $subject, 'sujeto_procesal');
        }
    }

    private function createNotificationsAndDispatch(Process $process, ProcessAction|ProcessSubject $notifiable, string $notificationType): void
    {

        $organizations = $process->organizations()->wherePivot('is_active', true)->get();

        foreach ($organizations as $organization) {
            $notification = OrganizationNotification::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'notifiable_id' => $notifiable->id,
                    'notifiable_type' => $notifiable->getMorphClass(),
                    'notification_type' => $notificationType,
                ],
                [
                    'is_viewed' => false,
                    'is_notified' => false,
                ]
            );

            dispatch(SendOrganizationNotificationJob::fromNotification($notification));
        }
    }
}

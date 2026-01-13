<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Actions;

use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationRepositoryInterface;
use Core\Shared\Infrastructure\Services\ChannelNotificationDispatcherService;
use Core\Shared\Domain\Enums\NotificationType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

readonly class HandleNewActionNotificationUseCase
{
    public function __construct(
        private OrganizationNotificationRepositoryInterface $notificationRepository,
        private ChannelNotificationDispatcherService $channelDispatcher
    ) {}

    /**
     * Maneja las notificaciones para nuevas actuaciones de un proceso
     */
    public function __invoke(Process $process, array $newActions): void
    {
        try {
            Log::channel('judicial_process_sync_job')->info("Verificando notificaciones para nuevas actuaciones del proceso {$process->process_id}");

            $interestedOrganizations = $process->organizations()->where('organization_processes.is_active', true)->get();

            if ($interestedOrganizations->isEmpty()) {
                Log::channel('judicial_process_sync_job')->info("No hay organizaciones interesadas en el proceso {$process->process_id}");
                return;
            }

            $organizationIds = $interestedOrganizations->pluck('id')->toArray();

            // Verificar qué organizaciones no han sido notificadas sobre nuevas actuaciones
            // Para nuevas actuaciones, verificamos por cada actuación individual
            $organizationsToNotify = [];
            foreach ($newActions as $action) {
                $notNotifiedForThisAction = $this->notificationRepository->getOrganizationsNotNotifiedByProcessId(
                    $action->id, // Usar el ID de la actuación
                    NotificationType::NEW_PROCESS_ACTION->value,
                    $organizationIds
                );
                $organizationsToNotify = array_unique(array_merge($organizationsToNotify, $notNotifiedForThisAction));
            }

            if (empty($organizationsToNotify)) {
                Log::channel('judicial_process_sync_job')->info("Todas las organizaciones ya fueron notificadas sobre nuevas actuaciones para el proceso {$process->process_id}");
                return;
            }

            Log::channel('judicial_process_sync_job')->info("📋 ENVIANDO NOTIFICACIONES DE NUEVAS ACTUACIONES", [
                'process_id' => $process->process_id,
                'organizations_to_notify' => count($organizationsToNotify),
                'new_actions_count' => count($newActions),
                'notification_type' => NotificationType::NEW_PROCESS_ACTION->value,
                'action_ids' => array_column($newActions, 'id')
            ]);

            $this->dispatchNewActionNotification($process, $newActions, $organizationsToNotify);

            Log::channel('judicial_process_sync_job')->info("✅ JOBS DE NUEVAS ACTUACIONES DESPACHADOS", [
                'process_id' => $process->process_id,
                'organizations_count' => count($organizationsToNotify),
                'new_actions_count' => count($newActions)
            ]);

        } catch (\Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error manejando notificaciones de nuevas actuaciones para el proceso {$process->process_id}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'process_id' => $process->id,
                'new_actions_count' => count($newActions),
            ]);
        }
    }

    /**
     * Despacha la notificación de nuevas actuaciones usando jobs por canal
     */
    private function dispatchNewActionNotification(Process $process, array $newActions, array $organizationIds): void
    {
        try {
            $processData = [
                'id' => $process->id,
                'process_id' => $process->process_id,
                'process_number' => $process->process_number,
                'court' => $process->court,
                'department' => $process->department,
                'process_type' => $process->process_type,
                'process_class' => $process->process_class,
            ];

            // Preparar datos de las nuevas actuaciones
            $actionsData = [];
            foreach ($newActions as $action) {
                $actionsData[] = [
                    'id' => $action->id,
                    'action_registration_id' => $action->action_registration_id,
                    'action_date' => $action->action_date->format('d/m/Y'),
                    'action' => $action->action,
                    'annotation' => $action->annotation,
                ];
            }

            // Crear notificaciones individuales para cada actuación
            foreach ($newActions as $action) {
                $actionData = [
                    'id' => $action->id,
                    'action_registration_id' => $action->action_registration_id,
                    'action_date' => $action->action_date->format('d/m/Y'),
                    'action' => $action->action,
                    'annotation' => $action->annotation,
                ];

                $additionalData = [
                    'new_actions_count' => 1, // Solo una actuación por notificación
                    'actions_data' => [$actionData],
                    'detected_at' => now()->format('d/m/Y H:i:s'),
                    'notification_key' => $process->process_id . '_new_action_' . $action->id,
                    'process_id' => $process->id,
                    'alert_type' => 'NEW_ACTION_DETECTION',
                    'notifiable_type' => 'Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction',
                    'notifiable_id' => $action->id, // ID específico de esta actuación
                ];

                // Obtener datos de las organizaciones
                $organizationsData = [];
                foreach ($organizationIds as $orgId) {
                    $org = $process->organizations()->find($orgId);
                    if ($org) {
                        $organizationsData[] = [
                            'id' => $org->id,
                            'name' => $org->name,
                            'slug' => $org->slug,
                            'type' => $org->type,
                        ];
                    }
                }

                $dispatchedJobs = $this->channelDispatcher->dispatchNotificationsForMultipleOrganizations(
                    NotificationType::NEW_PROCESS_ACTION->value,
                    $processData,
                    $organizationsData,
                    $additionalData,
                    1 // Base delay más corto para nuevas actuaciones
                );

                Log::channel('judicial_process_sync_job')->info("Jobs de nueva actuación individual despachados", [
                    'total_jobs_dispatched' => count($dispatchedJobs),
                    'organizations_count' => count($organizationsData),
                    'action_id' => $action->id,
                    'process_id' => $process->process_id,
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error despachando notificaciones de nuevas actuaciones por canal: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'process_id' => $process->process_id,
            ]);
        }
    }
}

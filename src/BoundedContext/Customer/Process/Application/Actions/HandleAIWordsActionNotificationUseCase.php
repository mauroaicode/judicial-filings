<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Actions;

use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationRepositoryInterface;
use Core\Shared\Infrastructure\Services\ChannelNotificationDispatcherService;
use Core\Shared\Domain\Enums\NotificationType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction;
use Illuminate\Support\Facades\Log;

readonly class HandleAIWordsActionNotificationUseCase
{
    public function __construct(
        private OrganizationNotificationRepositoryInterface $notificationRepository,
        private ChannelNotificationDispatcherService $channelDispatcher
    ) {}

    /**
     * Maneja las notificaciones para actuaciones que contienen palabras clave (CONSULTA/APELACIÓN)
     */
    public function __invoke(Process $process, array $aiWordsActions): void
    {
        try {

            // Obtener organizaciones interesadas en este proceso
            $interestedOrganizations = $process->organizations()->where('organization_processes.is_active', true)->get();

            if ($interestedOrganizations->isEmpty()) {
                Log::channel('judicial_process_sync_job')->info("No hay organizaciones interesadas en el proceso {$process->process_id}");
                return;
            }

            $organizationIds = $interestedOrganizations->pluck('id')->toArray();

            // Verificar qué organizaciones no han sido notificadas sobre actuaciones con palabras clave
            // Para alertas AI, verificamos por cada actuación individual

            $organizationsToNotify = [];

            foreach ($aiWordsActions as $action) {
                $notNotifiedForThisAction = $this->notificationRepository->getOrganizationsNotNotifiedByProcessId(
                    $action->id, // Usar el ID de la actuación
                    NotificationType::AI_WORDS_PROCESS_ACTION->value,
                    $organizationIds
                );
                $organizationsToNotify = array_unique(array_merge($organizationsToNotify, $notNotifiedForThisAction));
            }

            if (empty($organizationsToNotify)) {
                Log::channel('judicial_process_sync_job')->info("Todas las organizaciones ya fueron notificadas sobre actuaciones con palabras clave para el proceso {$process->process_id}");
                return;
            }

            Log::channel('judicial_process_sync_job')->info("🤖 ENVIANDO ALERTAS AI", [
                'process_id' => $process->process_id,
                'organizations_to_notify' => count($organizationsToNotify),
                'ai_words_actions_count' => count($aiWordsActions),
                'notification_type' => NotificationType::AI_WORDS_PROCESS_ACTION->value,
                'action_ids' => array_column($aiWordsActions, 'id')
            ]);

            $this->dispatchAIWordsActionNotification($process, $aiWordsActions, $organizationsToNotify);

            Log::channel('judicial_process_sync_job')->info("✅ JOBS DE ALERTA AI DESPACHADOS", [
                'process_id' => $process->process_id,
                'organizations_count' => count($organizationsToNotify),
                'ai_words_actions_count' => count($aiWordsActions)
            ]);

        } catch (\Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error manejando alertas AI para actuaciones con palabras clave del proceso {$process->process_id}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'process_id' => $process->id,
                'ai_words_actions_count' => count($aiWordsActions),
            ]);
        }
    }

    /**
     * Despacha la notificación de alerta AI usando jobs por canal
     */
    private function dispatchAIWordsActionNotification(Process $process, array $aiWordsActions, array $organizationIds): void
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

            // Preparar datos de las actuaciones con palabras clave
            $actionsData = [];
            $detectedWords = [];

            foreach ($aiWordsActions as $action) {
                $actionText = strtoupper($action->action . ' ' . ($action->annotation ?? ''));
                $foundWords = [];

                if (str_contains($actionText, 'CONSULTA')) {
                    $foundWords[] = 'CONSULTA';
                }
                if (str_contains($actionText, 'APELACIÓN')) {
                    $foundWords[] = 'APELACIÓN';
                }

                $actionsData[] = [
                    'id' => $action->id,
                    'action_registration_id' => $action->action_registration_id,
                    'action_date' => $action->action_date->format('d/m/Y'),
                    'action' => $action->action,
                    'annotation' => $action->annotation,
                    'detected_words' => $foundWords,
                ];

                $detectedWords = array_unique(array_merge($detectedWords, $foundWords));
            }

            // Crear notificaciones individuales para cada actuación con palabras clave
            foreach ($aiWordsActions as $action) {
                $actionText = strtoupper($action->action . ' ' . ($action->annotation ?? ''));
                $foundWords = [];

                if (str_contains($actionText, 'CONSULTA')) {
                    $foundWords[] = 'CONSULTA';
                }
                if (str_contains($actionText, 'APELACIÓN')) {
                    $foundWords[] = 'APELACIÓN';
                }

                $actionData = [
                    'id' => $action->id,
                    'action_registration_id' => $action->action_registration_id,
                    'action_date' => $action->action_date->format('d/m/Y'),
                    'action' => $action->action,
                    'annotation' => $action->annotation,
                    'detected_words' => $foundWords,
                ];

                $additionalData = [
                    'ai_words_actions_count' => 1, // Solo una actuación por notificación
                    'actions_data' => [$actionData],
                    'detected_words' => $foundWords,
                    'detected_at' => now()->format('d/m/Y H:i:s'),
                    'notification_key' => $process->process_id . '_ai_words_' . $action->id,
                    'process_id' => $process->id,
                    'alert_type' => 'AI_WORDS_DETECTION',
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
                    NotificationType::AI_WORDS_PROCESS_ACTION->value,
                    $processData,
                    $organizationsData,
                    $additionalData,
                    2 // Base delay más corto para alertas AI (2 segundos)
                );

                Log::channel('judicial_process_sync_job')->info("Jobs de alerta AI individual despachados", [
                    'total_jobs_dispatched' => count($dispatchedJobs),
                    'organizations_count' => count($organizationsData),
                    'action_id' => $action->id,
                    'detected_words' => $foundWords,
                    'process_id' => $process->process_id,
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error despachando alertas AI por canal: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'process_id' => $process->process_id,
            ]);
        }
    }
}

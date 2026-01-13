<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Services;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction;
use Exception;
use Illuminate\Support\Facades\Log;
use Core\Shared\Infrastructure\Services\JudicialBranchConsultService;
use Core\BoundedContext\Customer\Process\Domain\Repositories\{
    ProcessRepositoryInterface,
    ProcessActionRepositoryInterface,
    OrganizationNotificationRepositoryInterface
};
use Core\BoundedContext\Customer\Process\Application\Actions\{
    HandleNewActionNotificationUseCase,
    HandleAIWordsActionNotificationUseCase
};
use Core\Shared\Domain\Enums\NotificationType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction as ProcessActionModel;

readonly class ProcessActionSyncService
{
    public function __construct(
        private JudicialBranchConsultService                $judicialService,
        private ProcessRepositoryInterface                  $processRepository,
        private ProcessActionRepositoryInterface            $processActionRepository,
        private OrganizationNotificationRepositoryInterface $organizationNotificationRepository,
        private HandleNewActionNotificationUseCase          $handleNewActionNotificationUseCase,
        private HandleAIWordsActionNotificationUseCase      $handleAIWordsActionNotificationUseCase
    )
    {
    }

    /**
     * Sincroniza las actuaciones para una lista de procesos
     */
    public function syncProcessActions(array $processes): void
    {
        foreach ($processes as $processData) {
            try {
                $this->syncActionsForProcess($processData);

            } catch (Exception $e) {
                Log::channel('judicial_process_sync_job')->error("Error sincronizando actuaciones para proceso {$processData['idProceso']}: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'process_id' => $processData['idProceso'],
                    'process_number' => $processData['llaveProceso'] ?? 'N/A',
                ]);
            }
        }
    }

    /**
     * Sincroniza las actuaciones para un proceso específico
     */
    private function syncActionsForProcess(array $processData): void
    {
        $apiProcessId = $processData['idProceso'];


        $process = $this->processRepository->findByProcessId($apiProcessId);

        if (!$process) {
            return;
        }

        $actionsResponse = $this->judicialService->fetchActionByProcess($apiProcessId);

        if (!$actionsResponse->isSuccessful) {
            Log::channel('judicial_process_sync_job')->error("Error al consultar actuaciones para proceso {$apiProcessId}");
            return;
        }

        $apiActions = $actionsResponse->data;

        if (empty($apiActions)) {
            Log::channel('judicial_process_sync_job')->info("No se encontraron actuaciones para proceso {$apiProcessId}");
            return;
        }

        $newActions = [];
        $aiWordsActions = [];

        foreach ($apiActions as $actionData) {
            $actionRegistrationId = $actionData['idRegActuacion'] ?? null;

            if (!$actionRegistrationId) {
                Log::channel('judicial_process_sync_job')->warning("Actuación sin ID de registro, saltando", [
                    'process_id' => $apiProcessId,
                    'action_data' => $actionData
                ]);
                continue;
            }

            if ($this->processActionRepository->existsByRegistrationId($actionRegistrationId)) {
                Log::channel('judicial_process_sync_job')->debug("Actuación {$actionRegistrationId} ya existe, saltando");
                continue;
            }

            $newAction = $this->createProcessAction($process->id, $actionData);

            if ($newAction) {
                $newActions[] = $newAction;

                // Verificar si contiene palabras clave para alerta AI
                if ($this->containsAIWords($actionData)) {
                    $aiWordsActions[] = $newAction;
                    Log::channel('judicial_process_sync_job')->info("🔍 PALABRA CLAVE DETECTADA", [
                        'action_id' => $newAction->id,
                        'action_text' => $actionData['actuacion'] ?? '',
                        'annotation_text' => $actionData['anotacion'] ?? '',
                        'process_id' => $process->id
                    ]);
                }
            }
        }

        // 4. Crear registros de notificación y enviar notificaciones si hay nuevas actuaciones
        if (!empty($newActions)) {
            Log::channel('judicial_process_sync_job')->info("📋 PROCESANDO NUEVAS ACTUACIONES", [
                'process_id' => $process->id,
                'new_actions_count' => count($newActions),
                'notification_type' => NotificationType::NEW_PROCESS_ACTION->value,
                'action_ids' => array_column($newActions, 'id'),
                'action_descriptions' => array_column($newActions, 'action')
            ]);

            $this->createOrganizationNotificationRecordsForActions($process, $newActions, NotificationType::NEW_PROCESS_ACTION->value);

            $this->handleNewActionNotificationUseCase->__invoke($process, $newActions);
        }

        // 5. Crear registros de notificación y enviar notificaciones especiales para actuaciones con palabras clave
        if (!empty($aiWordsActions)) {
            Log::channel('judicial_process_sync_job')->info("🤖 DETECTADAS ACTUACIONES CON PALABRAS CLAVE", [
                'process_id' => $process->id,
                'ai_words_actions_count' => count($aiWordsActions),
                'notification_type' => NotificationType::AI_WORDS_PROCESS_ACTION->value,
                'action_ids' => array_column($aiWordsActions, 'id'),
                'action_descriptions' => array_column($aiWordsActions, 'action')
            ]);

            // Crear registros de OrganizationNotification con is_notified = 0 para permitir reintentos
            Log::channel('judicial_process_sync_job')->info("📝 INICIANDO CREACIÓN DE REGISTROS PARA AI WORDS", [
                'process_id' => $process->id,
                'actions_count' => count($aiWordsActions),
                'notification_type' => NotificationType::AI_WORDS_PROCESS_ACTION->value
            ]);
            
            $this->createOrganizationNotificationRecordsForActions($process, $aiWordsActions, NotificationType::AI_WORDS_PROCESS_ACTION->value);

            Log::channel('judicial_process_sync_job')->info("📤 INICIANDO ENVÍO DE NOTIFICACIONES AI WORDS", [
                'process_id' => $process->id,
                'actions_count' => count($aiWordsActions)
            ]);

            $this->handleAIWordsActionNotificationUseCase->__invoke($process, $aiWordsActions);
        }

        Log::channel('judicial_process_sync_job')->info("Sincronización de actuaciones completada para proceso {$apiProcessId}", [
            'new_actions_count' => count($newActions),
            'ai_words_actions_count' => count($aiWordsActions)
        ]);
    }

    /**
     * Crea una nueva actuación en la base de datos
     */
    private function createProcessAction(string $processId, array $actionData): ?ProcessAction
    {
        try {
            $actionDataForDB = [
                'process_id' => $processId,
                'action_registration_id' => $actionData['idRegActuacion'],
                'action_date' => $actionData['fechaActuacion'] ?? null,
                'action' => $actionData['actuacion'] ?? '',
                'annotation' => $actionData['anotacion'] ?? null,
                'start_date' => $actionData['fechaInicio'] ?? null,
                'end_date' => $actionData['fechaFin'] ?? null,
                'registration_date' => $actionData['fechaRegistro'] ?? null,
            ];

            $action = $this->processActionRepository->create($actionDataForDB);

            Log::channel('judicial_process_sync_job')->info("Actuación creada exitosamente", [
                'action_id' => $action->id,
                'registration_id' => $action->action_registration_id,
                'process_id' => $processId
            ]);

            return $action;

        } catch (Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error creando actuación: " . $e->getMessage(), [
                'process_id' => $processId,
                'action_data' => $actionData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verifica si la actuación contiene palabras clave para alerta AI
     */
    private function containsAIWords(array $actionData): bool
    {
        $actionText = strtoupper(trim($actionData['actuacion'] ?? ''));
        $annotationText = strtoupper(trim($actionData['anotacion'] ?? ''));

        // Normalizar caracteres especiales para evitar problemas de codificación
        $actionText = $this->normalizeText($actionText);
        $annotationText = $this->normalizeText($annotationText);

        // Palabras clave con variaciones de codificación
        $aiWords = [
            'CONSULTA', 'CONSULTA', // Normal
            'APELACION', 'APELACIÓN', 'APELACIóN', 'APELACIÓN' // Variaciones de APELACIÓN
        ];

        Log::channel('judicial_process_sync_job')->info("🔍 VERIFICANDO PALABRAS CLAVE", [
            'action_text' => $actionText,
            'annotation_text' => $annotationText,
            'ai_words' => $aiWords
        ]);

        foreach ($aiWords as $word) {
            $normalizedWord = $this->normalizeText($word);
            $actionContains = str_contains($actionText, $normalizedWord);
            $annotationContains = str_contains($annotationText, $normalizedWord);
            
            if ($actionContains || $annotationContains) {
                Log::channel('judicial_process_sync_job')->info("✅ PALABRA CLAVE ENCONTRADA", [
                    'word' => $word,
                    'normalized_word' => $normalizedWord,
                    'action_contains' => $actionContains,
                    'annotation_contains' => $annotationContains,
                    'action_text' => $actionText,
                    'annotation_text' => $annotationText
                ]);
                return true;
            }
        }

        Log::channel('judicial_process_sync_job')->info("❌ NO SE ENCONTRARON PALABRAS CLAVE", [
            'action_text' => $actionText,
            'annotation_text' => $annotationText
        ]);

        return false;
    }

    /**
     * Normaliza texto para evitar problemas de codificación
     */
    private function normalizeText(string $text): string
    {
        // Convertir caracteres especiales a su forma básica
        $text = str_replace(['ó', 'Ó', 'ó', 'Ó'], 'O', $text);
        $text = str_replace(['á', 'Á', 'á', 'Á'], 'A', $text);
        $text = str_replace(['é', 'É', 'é', 'É'], 'E', $text);
        $text = str_replace(['í', 'Í', 'í', 'Í'], 'I', $text);
        $text = str_replace(['ú', 'Ú', 'ú', 'Ú'], 'U', $text);
        $text = str_replace(['ñ', 'Ñ', 'ñ', 'Ñ'], 'N', $text);
        
        return $text;
    }

    /**
     * Create OrganizationNotification records for specific actions with is_notified = 0 to allow retries
     */
    private function createOrganizationNotificationRecordsForActions(Process $process, array $actions, string $notificationType): void
    {
        try {
            Log::channel('judicial_process_sync_job')->info("🔍 INICIANDO CREACIÓN DE REGISTROS DE NOTIFICACIÓN", [
                'process_id' => $process->id,
                'notification_type' => $notificationType,
                'actions_count' => count($actions)
            ]);

            $interestedOrganizations = $process->organizations()->where('organization_processes.is_active', true)->get();

            if ($interestedOrganizations->isEmpty()) {
                Log::channel('judicial_process_sync_job')->warning("❌ No hay organizaciones interesadas para crear registros de notificación", [
                    'process_id' => $process->id,
                    'notification_type' => $notificationType,
                    'actions_count' => count($actions)
                ]);
                return;
            }

            $organizationIds = $interestedOrganizations->pluck('id')->toArray();

            Log::channel('judicial_process_sync_job')->info("📋 ORGANIZACIONES INTERESADAS ENCONTRADAS", [
                'process_id' => $process->id,
                'notification_type' => $notificationType,
                'organizations_count' => count($organizationIds),
                'organization_ids' => $organizationIds
            ]);

            foreach ($actions as $action) {
                Log::channel('judicial_process_sync_job')->info("📝 CREANDO REGISTROS PARA ACCIÓN", [
                    'action_id' => $action->id,
                    'action_description' => $action->action,
                    'notification_type' => $notificationType,
                    'organizations_count' => count($organizationIds)
                ]);

                $this->organizationNotificationRepository->createNotificationRecordsForOrganizations(
                    $action->id,
                    ProcessActionModel::class,
                    $notificationType,
                    $organizationIds
                );

                Log::channel('judicial_process_sync_job')->info("✅ REGISTROS CREADOS PARA ACCIÓN", [
                    'action_id' => $action->id,
                    'notification_type' => $notificationType
                ]);
            }

            Log::channel('judicial_process_sync_job')->info("🎉 TODOS LOS REGISTROS CREADOS EXITOSAMENTE", [
                'process_id' => $process->id,
                'notification_type' => $notificationType,
                'actions_count' => count($actions),
                'organizations_count' => count($organizationIds)
            ]);

        } catch (\Exception $e) {
            Log::channel('judicial_process_sync_job')->error("❌ ERROR creando registros de OrganizationNotification para actuaciones: " . $e->getMessage(), [
                'process_id' => $process->id,
                'notification_type' => $notificationType,
                'actions_count' => count($actions),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

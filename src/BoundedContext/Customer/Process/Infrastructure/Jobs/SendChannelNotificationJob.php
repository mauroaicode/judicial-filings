<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Jobs;

use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Core\BoundedContext\Customer\Process\Domain\Notification\NotificationChannelInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\HistoryOrganizationChannelNotification;

/**
 * Job for sending notifications through a specific channel to a specific recipient
 */
class SendChannelNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 500;
    public $tries = 5;
    public $backoff = [30, 60, 120, 300]; // Exponential backoff: 30s, 1m, 2m, 5m
    public $maxExceptions = 3;


    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $notificationType,
        private readonly array  $processData,
        private readonly array  $organizationData,
        private readonly array  $additionalData,
        private readonly string $channelClass,
        private readonly string $channelValue,
        private readonly int    $priority,
        private readonly int    $delaySeconds = 4
    )
    {
        // Determine queue based on channel type
        $queueName = $this->getQueueNameForChannel($channelClass);

        // Adjust delays and timeouts based on channel type
        $this->adjustSettingsForChannel($channelClass);

        $this->onQueue($queueName);
    }

    /**
     * Determine the appropriate queue name based on channel type
     */
    private function getQueueNameForChannel(string $channelClass): string
    {
        return match (true) {
            str_contains($channelClass, 'EmailNotificationChannel') => 'notifications-email',
            str_contains($channelClass, 'WhatsAppNotificationChannel') => 'notifications-whatsapp',
            str_contains($channelClass, 'SmsNotificationChannel') => 'notifications-sms',
            str_contains($channelClass, 'InternalNotificationChannel') => 'notifications-internal',
            default => 'notifications'
        };
    }

    /**
     * Adjust job settings based on channel type
     */
    private function adjustSettingsForChannel(string $channelClass): void
    {
        if (str_contains($channelClass, 'EmailNotificationChannel')) {
            // Email channels: higher delay, more attempts, longer timeout
            $this->delay($this->delaySeconds * 3); // 3x delay for emails
            $this->timeout = 120; // 2 minutes
            $this->tries = 5;
            $this->maxExceptions = 3;
        } elseif (str_contains($channelClass, 'WhatsAppNotificationChannel')) {
            // WhatsApp: moderate settings
            $this->delay($this->delaySeconds * 2); // 2x delay
            $this->timeout = 60; // 1 minute
            $this->tries = 3;
            $this->maxExceptions = 2;
        } elseif (str_contains($channelClass, 'SmsNotificationChannel')) {
            // SMS: moderate settings
            $this->delay($this->delaySeconds * 2); // 2x delay
            $this->timeout = 60; // 1 minute
            $this->tries = 3;
            $this->maxExceptions = 2;
        } elseif (str_contains($channelClass, 'InternalNotificationChannel')) {
            // Internal: fast processing
            $this->delay($this->delaySeconds); // No extra delay
            $this->timeout = 30; // 30 seconds
            $this->tries = 2;
            $this->maxExceptions = 1;
        } else {
            // Default settings
            $this->delay($this->delaySeconds);
        }
    }

    /**
     * Get the appropriate log channel based on notification type
     */
    private function getLogChannelForNotification(): string
    {
        return match (true) {
            str_contains($this->channelClass, 'EmailNotificationChannel') => 'notifications-email',
            str_contains($this->channelClass, 'WhatsAppNotificationChannel') => 'notifications-whatsapp',
            str_contains($this->channelClass, 'SmsNotificationChannel') => 'notifications-sms',
            str_contains($this->channelClass, 'InternalNotificationChannel') => 'notifications-internal',
            default => 'notifications'
        };
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(): void
    {
        // Crear registro de historial ANTES de intentar enviar
        $historyRecord = $this->createHistoryRecord();

        try {

            $channel = app($this->channelClass);

            if (!$channel instanceof NotificationChannelInterface) {
                throw new InvalidArgumentException("La clase {$this->channelClass} no implementa NotificationChannelInterface");
            }

            $notificationData = new NotificationData(
                $this->notificationType,
                $this->processData,
                [$this->organizationData], // Solo una organización por job
                $this->additionalData,
                $this->channelValue
            );


            if ($this->isAlreadyNotified()) {
                $logChannel = $this->getLogChannelForNotification();
                Log::channel($logChannel)->info('Ya se notificó a este canal específico, saltando envío', [
                    'organization_id' => $this->organizationData['id'],
                    'process_id' => $this->processData['id'],
                    'channel_value' => $this->channelValue,
                    'channel' => $channel->getChannelName(),
                ]);
                return;
            }

            $success = $channel->send($notificationData);

            if ($success) {
                Log::channel($this->getLogChannelForNotification())->info('✅ Notificación enviada exitosamente por canal individual', [
                    'type' => $this->notificationType,
                    'channel' => $channel->getChannelName(),
                    'channel_value' => $this->channelValue,
                    'priority' => $this->priority,
                    'process_id' => $notificationData->getProcessId(),
                    'organization_id' => $this->organizationData['id'],
                    'notifiable_id' => $this->getNotifiableId(),
                    'notifiable_type' => $this->getNotifiableType(),
                    'attempt' => $this->attempts()
                ]);

                // Actualizar historial como exitoso
                $this->updateHistoryRecordAsSuccess($historyRecord);

                $this->markAsNotified();

            } else {
                Log::channel($this->getLogChannelForNotification())->error('Fallo en envío de notificación por canal individual', [
                    'type' => $this->notificationType,
                    'channel' => $channel->getChannelName(),
                    'channel_value' => $this->channelValue,
                    'priority' => $this->priority,
                    'process_id' => $notificationData->getProcessId(),
                ]);

                Log::channel($this->getLogChannelForNotification())->warning('No se marcó como notificado debido al fallo en el envío', [
                    'channel_value' => $this->channelValue,
                    'priority' => $this->priority,
                ]);

                Log::channel($this->getLogChannelForNotification())->error('El canal retornó false - esto no debería pasar si el EmailNotificationChannel lanza excepciones', [
                    'channel' => $channel->getChannelName(),
                    'channel_value' => $this->channelValue,
                    'organization_id' => $this->organizationData['id'],
                ]);

                throw new \Exception("El canal {$channel->getChannelName()} retornó false - verificar logs para el error real del SMTP");

            }

        } catch (Exception $e) {
            $isRateLimitError = $this->isRateLimitError($e);

            $this->updateHistoryRecordAsFailed($historyRecord, $e->getMessage());

            // Log principal del error con todos los detalles
            Log::channel($this->getLogChannelForNotification())->error('Error en SendChannelNotificationJob: ' . $e->getMessage(), [
                'type' => $this->notificationType,
                'channel_class' => $this->channelClass,
                'channel_value' => $this->channelValue,
                'priority' => $this->priority,
                'process_id' => $this->processData['id'] ?? 'N/A',
                'is_rate_limit_error' => $isRateLimitError,
                'attempts' => $this->attempts(),
                'max_attempts' => $this->tries,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'exception_class' => get_class($e),
            ]);

            // Si es error de rate limiting y aún tenemos intentos, reintentar con delay
            if ($isRateLimitError && $this->attempts() < $this->tries) {
                $delayMinutes = $this->attempts() * 2; // 2, 4, 6, 8 minutos
                Log::channel($this->getLogChannelForNotification())->info("🔄 Reintentando en {$delayMinutes} minutos debido a rate limiting", [
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                    'organization_id' => $this->organizationData['id'] ?? 'N/A'
                ]);

                $this->release($delayMinutes * 60); // Convertir a segundos
                return;
            }

            throw $e;
        }
    }

    /**
     * Check if the error is related to rate limiting
     */
    private function isRateLimitError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $rateLimitKeywords = [
            'too many emails',
            'rate limit',
            'too many requests',
            'quota exceeded',
            'limit exceeded'
        ];

        foreach ($rateLimitKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if already notified to avoid duplicates
     * Ahora verifica por email específico, no solo por organización
     */
    private function isAlreadyNotified(): bool
    {
        try {
            // Verificar si ya se notificó a este EMAIL específico para este proceso
            // En lugar de verificar toda la organización, verificamos el email específico
            // Esto permite múltiples emails por organización pero evita duplicados del mismo email

            // Por ahora, permitimos múltiples notificaciones por organización
            // TODO: Implementar verificación por email específico si es necesario
            return false;

        } catch (Exception $e) {
            Log::channel($this->getLogChannelForNotification())->warning('Error verificando si ya se notificó: ' . $e->getMessage(), [
                'organization_id' => $this->organizationData['id'],
                'process_id' => $this->processData['id'],
                'channel_value' => $this->channelValue,
            ]);
            return false;
        }
    }

    /**
     * Create history record for this notification attempt
     */
    private function createHistoryRecord(): HistoryOrganizationChannelNotification
    {
        // Obtener el ID del canal de notificación
        $channelId = $this->getChannelId();

        $historyRecord = new HistoryOrganizationChannelNotification();
        $historyRecord->id = Str::uuid()->toString();
        $historyRecord->organization_notification_channel_id = $channelId;
        $historyRecord->notifiable_id = $this->processData['id'];
        $historyRecord->notifiable_type = 'Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process';
        $historyRecord->notification_type = $this->notificationType;
        $historyRecord->is_notified = false;
        $historyRecord->notified_at = null;

        $historyRecord->save();

        Log::channel($this->getLogChannelForNotification())->info('Registro de historial creado', [
            'history_id' => $historyRecord->id,
            'channel_id' => $channelId,
            'process_id' => $this->processData['id'],
            'notification_type' => $this->notificationType
        ]);

        return $historyRecord;
    }

    /**
     * Update history record as successful
     */
    private function updateHistoryRecordAsSuccess(HistoryOrganizationChannelNotification $historyRecord): void
    {
        $historyRecord->is_notified = true;
        $historyRecord->notified_at = now();
        $historyRecord->save();

//        Log::channel($this->getLogChannelForNotification())->info('Historial actualizado como exitoso', [
//            'history_id' => $historyRecord->id,
//            'process_id' => $this->processData['id']
//        ]);
    }

    /**
     * Update history record as failed
     */
    private function updateHistoryRecordAsFailed(HistoryOrganizationChannelNotification $historyRecord, string $errorMessage): void
    {
        $historyRecord->is_notified = false;
        $historyRecord->notified_at = null;
        $historyRecord->save();

        Log::channel($this->getLogChannelForNotification())->error('Historial actualizado como fallido', [
            'history_id' => $historyRecord->id,
            'process_id' => $this->processData['id'],
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Get the channel ID for the current notification
     */
    private function getChannelId(): string
    {
        // Buscar el canal por el valor del canal (email, etc.)
        $channel = OrganizationNotificationChannel::query()
            ->where('channel_value', $this->channelValue)
            ->where('organization_id', $this->organizationData['id'])
            ->first();

        return $channel ? $channel->id : Str::uuid()->toString();
    }

    /**
     * Mark the notification as successfully sent
     */
    private function markAsNotified(): void
    {
        try {
            // Determinar el tipo de notificable basado en el tipo de notificación
            $notifiableType = $this->getNotifiableType();
            $notifiableId = $this->getNotifiableId();

            Log::channel($this->getLogChannelForNotification())->info('🔍 Intentando marcar como notificado', [
                'organization_id' => $this->organizationData['id'],
                'notifiable_id' => $notifiableId,
                'notifiable_type' => $notifiableType,
                'notification_type' => $this->notificationType,
                'channel_value' => $this->channelValue
            ]);

            // Actualizar registro existente en OrganizationNotification
            $updated = OrganizationNotification::query()
                ->where('organization_id', $this->organizationData['id'])
                ->where('notifiable_id', $notifiableId)
                ->where('notifiable_type', $notifiableType)
                ->where('notification_type', $this->notificationType)
                ->update([
                    'is_notified' => true,
                    'notified_at' => now()
                ]);

            if ($updated) {
                Log::channel($this->getLogChannelForNotification())->info('✅ Notificación marcada como enviada exitosamente en la base de datos', [
                    'channel_value' => $this->channelValue,
                    'priority' => $this->priority,
                    'organization_id' => $this->organizationData['id'],
                    'notifiable_id' => $notifiableId,
                    'notifiable_type' => $notifiableType,
                    'notification_type' => $this->notificationType,
                    'updated_records' => $updated
                ]);
            } else {
                Log::channel($this->getLogChannelForNotification())->warning('❌ No se pudo actualizar el registro de notificación', [
                    'channel_value' => $this->channelValue,
                    'organization_id' => $this->organizationData['id'],
                    'notifiable_id' => $notifiableId,
                    'notifiable_type' => $notifiableType,
                    'notification_type' => $this->notificationType,
                ]);

                // Verificar si el registro existe
                $existingRecord = OrganizationNotification::query()
                    ->where('organization_id', $this->organizationData['id'])
                    ->where('notifiable_id', $notifiableId)
                    ->where('notifiable_type', $notifiableType)
                    ->where('notification_type', $this->notificationType)
                    ->first();

                if ($existingRecord) {
                    Log::channel($this->getLogChannelForNotification())->info('📋 Registro existe pero no se pudo actualizar', [
                        'record_id' => $existingRecord->id,
                        'is_notified' => $existingRecord->is_notified,
                        'notified_at' => $existingRecord->notified_at
                    ]);
                } else {
                    Log::channel($this->getLogChannelForNotification())->warning('🔍 Registro no encontrado en la base de datos', [
                        'search_criteria' => [
                            'organization_id' => $this->organizationData['id'],
                            'notifiable_id' => $notifiableId,
                            'notifiable_type' => $notifiableType,
                            'notification_type' => $this->notificationType
                        ]
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::channel($this->getLogChannelForNotification())->warning('❌ No se pudo marcar como notificado en la base de datos: ' . $e->getMessage(), [
                'channel_value' => $this->channelValue,
                'organization_id' => $this->organizationData['id'],
                'notifiable_id' => $notifiableId ?? 'unknown',
                'notifiable_type' => $notifiableType ?? 'unknown',
                'notification_type' => $this->notificationType,
                'error_details' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the notifiable type based on notification type
     */
    private function getNotifiableType(): string
    {
        // Para notificaciones de actuaciones, usar ProcessAction
        if (in_array($this->notificationType, ['new_process_action', 'ai_words_process_action'])) {
            return 'Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction';
        }

        // Para notificaciones de múltiples instancias, usar Process
        if ($this->notificationType === 'multiple_instances') {
            return 'Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process';
        }

        // Por defecto, usar Process
        return 'Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process';
    }

    /**
     * Get the notifiable ID based on notification type
     */
    private function getNotifiableId(): string
    {
        // Para notificaciones de actuaciones, usar el ID de la actuación
        if (in_array($this->notificationType, ['new_process_action', 'ai_words_process_action'])) {
            // El ID de la actuación debería estar en additionalData como notifiable_id
            $notifiableId = $this->additionalData['notifiable_id'] ?? null;

            if (!$notifiableId) {
                Log::channel($this->getLogChannelForNotification())->warning('⚠️ No se encontró notifiable_id en additionalData', [
                    'notification_type' => $this->notificationType,
                    'additional_data_keys' => array_keys($this->additionalData),
                    'process_id' => $this->processData['id'] ?? 'unknown'
                ]);
                // Fallback al ID del proceso si no se encuentra el notifiable_id
                return $this->processData['id'] ?? 'unknown';
            }

            return $notifiableId;
        }

        // Para notificaciones de múltiples instancias, usar el ID del proceso
        return $this->processData['id'] ?? 'unknown';
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'notification',
            'channel',
            $this->channelClass,
            $this->notificationType,
            'priority_' . $this->priority,
        ];
    }

    /**
     * Get the display name for the job.
     */
    public function displayName(): string
    {
        return "Send {$this->notificationType} notification via {$this->channelClass} to {$this->channelValue}";
    }
}

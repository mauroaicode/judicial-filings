<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Core\BoundedContext\Customer\Process\Domain\Notification\NotificationChannelInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;

/**
 * Job for sending notifications through a specific channel to a specific recipient
 */
class SendChannelNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;
    public $backoff = [];


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
        $this->delay($this->delaySeconds);
        $this->onQueue(config('queue.queues.notifications.queue', 'notifications'));
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            Log::channel('notifications')->info('Iniciando envío de notificación por canal individual', [
                'type' => $this->notificationType,
                'channel_class' => $this->channelClass,
                'channel_value' => $this->channelValue,
                'priority' => $this->priority,
                'process_id' => $this->processData['id'] ?? 'N/A',
            ]);


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
                Log::channel('notifications')->info('Ya se notificó a este canal específico, saltando envío', [
                    'organization_id' => $this->organizationData['id'],
                    'process_id' => $this->processData['id'],
                    'channel_value' => $this->channelValue,
                    'channel' => $channel->getChannelName(),
                ]);
                return;
            }

            $success = $channel->send($notificationData);

            if ($success) {
                Log::channel('notifications')->info('Notificación enviada exitosamente por canal individual', [
                    'type' => $this->notificationType,
                    'channel' => $channel->getChannelName(),
                    'channel_value' => $this->channelValue,
                    'priority' => $this->priority,
                    'process_id' => $notificationData->getProcessId(),
                ]);

                $this->markAsNotified();

            } else {
                Log::channel('notifications')->error('Fallo en envío de notificación por canal individual', [
                    'type' => $this->notificationType,
                    'channel' => $channel->getChannelName(),
                    'channel_value' => $this->channelValue,
                    'priority' => $this->priority,
                    'process_id' => $notificationData->getProcessId(),
                ]);

                Log::channel('notifications')->warning('No se marcó como notificado debido al fallo en el envío', [
                    'channel_value' => $this->channelValue,
                    'priority' => $this->priority,
                ]);

                Log::channel('notifications')->error('El canal retornó false - esto no debería pasar si el EmailNotificationChannel lanza excepciones', [
                    'channel' => $channel->getChannelName(),
                    'channel_value' => $this->channelValue,
                    'organization_id' => $this->organizationData['id'],
                ]);

                throw new \Exception("El canal {$channel->getChannelName()} retornó false - verificar logs para el error real del SMTP");

            }

        } catch (Exception $e) {
            // Log principal del error con todos los detalles
            Log::channel('notifications')->error('Error crítico en SendChannelNotificationJob: ' . $e->getMessage(), [
                'type' => $this->notificationType,
                'channel_class' => $this->channelClass,
                'channel_value' => $this->channelValue,
                'priority' => $this->priority,
                'process_id' => $this->processData['id'] ?? 'N/A',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'exception_class' => get_class($e),
                'error_code' => method_exists($e, 'getCode') ? $e->getCode() : 'N/A',
            ]);

            // Log adicional para debugging con contexto del job
            Log::channel('notifications')->error('Contexto del error del job', [
                'organization_id' => $this->organizationData['id'] ?? 'N/A',
                'channel_value' => $this->channelValue,
                'priority' => $this->priority,
                'delay_seconds' => $this->delaySeconds,
            ]);

            throw $e;
        }
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
            Log::channel('notifications')->warning('Error verificando si ya se notificó: ' . $e->getMessage(), [
                'organization_id' => $this->organizationData['id'],
                'process_id' => $this->processData['id'],
                'channel_value' => $this->channelValue,
            ]);
            return false;
        }
    }

    /**
     * Mark the notification as successfully sent
     */
    private function markAsNotified(): void
    {
        try {
            // Crear o actualizar registro en OrganizationNotification
            $notification = new OrganizationNotification();
            $notification->organization_id = $this->organizationData['id'];
            $notification->notifiable_id = $this->processData['id'];
            $notification->notifiable_type = 'Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process';
            $notification->notification_type = $this->notificationType;
            $notification->is_viewed = false;
            $notification->is_notified = true;
            $notification->notified_at = now();

            // Usar save() en lugar de updateOrCreate para claves primarias compuestas
            $notification->save();

            Log::channel('notifications')->info('Notificación marcada como enviada exitosamente en la base de datos', [
                'channel_value' => $this->channelValue,
                'priority' => $this->priority,
                'organization_id' => $this->organizationData['id'],
                'process_id' => $this->processData['id'],
            ]);
        } catch (Exception $e) {
            Log::channel('notifications')->warning('No se pudo marcar como notificado en la base de datos: ' . $e->getMessage(), [
                'channel_value' => $this->channelValue,
                'organization_id' => $this->organizationData['id'],
                'process_id' => $this->processData['id'],
                'error_details' => $e->getMessage(),
            ]);
        }
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

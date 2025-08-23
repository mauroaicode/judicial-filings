<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Jobs;

use Core\BoundedContext\Customer\Process\Domain\Notification\NotificationChannelInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;
use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationRepositoryInterface;
use Core\Shared\Domain\Enums\NotificationType;
use Core\Shared\Domain\Repositories\OrganizationRepositoryInterface;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    private readonly string $type;
    private readonly array $data;
    private readonly array $channels;

    public function __construct(
        string $type,
        array $data,
        array $channels
    ) {
        $this->type = $type;
        $this->data = $data;
        $this->channels = $channels;
        $this->onQueue(config('queue.queues.notifications.queue'));
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(
        OrganizationRepositoryInterface $organizationRepository,
        OrganizationNotificationRepositoryInterface $notificationRepository
    ): void
    {
        try {
            Log::channel('notifications')->info('Iniciando envío de notificación', [
                'type' => $this->type,
                'channels' => $this->channels,
            ]);

            $organizations = $this->data['organizations'];

            if (is_string($organizations[0] ?? null)) {
                $organizations = $organizationRepository->findByIds($organizations);
            }

            $organizationsArray = $organizations instanceof Collection
                ? $organizations->toArray()
                : $organizations;

            $notificationData = new NotificationData(
                $this->type,
                $this->data['process'],
                $organizationsArray,
                $this->data['additionalData'] ?? []
            );

            $successCount = 0;
            $failureCount = 0;
            $allChannelsSuccessful = true;

            foreach ($this->channels as $channelClass) {
                try {
                    $channel = app($channelClass);

                    if (!$channel instanceof NotificationChannelInterface) {
                        throw new InvalidArgumentException("La clase {$channelClass} no implementa NotificationChannelInterface");
                    }

                    $success = $channel->send($notificationData);

                    if ($success) {
                        $successCount++;
                        Log::channel('notifications')->info('Notificación enviada exitosamente', [
                            'type' => $this->type,
                            'channel' => $channel->getChannelName(),
                            'process_id' => $notificationData->getProcess()->id,
                        ]);
                    } else {
                        $failureCount++;
                        $allChannelsSuccessful = false;
                        Log::channel('notifications')->warning('Notificación falló', [
                            'type' => $this->type,
                            'channel' => $channel->getChannelName(),
                            'process_id' => $notificationData->getProcess()->id,
                        ]);
                    }

                } catch (Exception $e) {
                    $failureCount++;
                    $allChannelsSuccessful = false;
                    Log::channel('notifications')->error('Error en canal ' . $channelClass . ': ' . $e->getMessage(), [
                        'type' => $this->type,
                        'channel' => $channelClass,
                        'process_id' => $notificationData->getProcess()->id,
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if ($allChannelsSuccessful && $successCount > 0) {
                $this->markOrganizationsAsNotified($notificationRepository, $notificationData);

                Log::channel('notifications')->info('Notificaciones marcadas como enviadas exitosamente', [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                ]);
            } else {
                Log::channel('notifications')->error('Todos los canales fallaron. No se marca como notificado', [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                ]);

                throw new Exception("Todos los canales fallaron. Reintentando...");
            }

            Log::channel('notifications')->info('Envío de notificación completado', [
                'type' => $this->type,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'total_channels' => count($this->channels),
                'result' => $successCount > 0 ? 'PARTIAL_SUCCESS' : 'COMPLETE_FAILURE',
                'message' => $successCount > 0
                    ? "Notificación enviada por {$successCount} canal(es) de " . count($this->channels)
                    : "Todos los canales fallaron"
            ]);

        } catch (Exception $e) {
            Log::channel('notifications')->error('Error crítico en SendNotificationJob: ' . $e->getMessage(), [
                'type' => $this->type,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Marca las organizaciones como notificadas SOLO si se envió exitosamente
     */
    private function markOrganizationsAsNotified(
        OrganizationNotificationRepositoryInterface $notificationRepository,
        NotificationData $notificationData
    ): void {
        try {
            $processId = $notificationData->getProcess()->id;
            $filingNumber = $notificationData->getAdditionalData()['filing_number'] ?? null;

            if ($processId && $filingNumber) {
                $organizationIds = collect($notificationData->getOrganizations())->pluck('id')->toArray();

                foreach ($organizationIds as $organizationId) {
                    $notificationRepository->markOrganizationAsNotified(
                        $organizationId,
                        'Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process',
                        $processId,
                        NotificationType::MULTIPLE_INSTANCE->value
                    );
                }

                Log::channel('notifications')->info("Organizaciones marcadas como notificadas para proceso {$processId} (radicado: {$filingNumber}). Total: " . count($organizationIds));
            }
        } catch (\Exception $e) {
            Log::channel('notifications')->error('Error marcando organizaciones como notificadas: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

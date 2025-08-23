<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Channels;

use Core\BoundedContext\Customer\Process\Domain\Notification\NotificationChannelInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;
use Illuminate\Support\Facades\Log;

readonly class InternalNotificationChannel implements NotificationChannelInterface
{
    /**
     * Send notification through an internal channel
     */
    public function send(NotificationData $data): bool
    {
        try {
            $internalChannels = $data->getOrganizationInternalChannels();

            if (empty($internalChannels)) {
                Log::channel('notifications')->warning('No se encontraron canales internos activos para enviar notificación', [
                    'type' => $data->getType(),
                    'process_id' => $data->getProcess()->id,
                    'organizations_count' => count($data->getOrganizations()),
                ]);
                return false;
            }

            // TODO: Implementar lógica de notificaciones internas
            // Por ejemplo: notificaciones push, notificaciones en dashboard, etc.
            foreach ($internalChannels as $channel) {
                Log::channel('notifications')->info('Simulando envío de notificación interna', [
                    'type' => $data->getType(),
                    'internal_channel' => $channel,
                    'process_id' => $data->getProcess()->id,
                ]);
            }

            Log::channel('notifications')->info('Notificación enviada a todos los canales internos activos', [
                'type' => $data->getType(),
                'internal_channels_count' => count($internalChannels),
                'process_id' => $data->getProcess()->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::channel('notifications')->error('Error enviando notificación interna: ' . $e->getMessage(), [
                'type' => $data->getType(),
                'process_id' => $data->getProcess()->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get channel name
     */
    public function getChannelName(): string
    {
        return 'internal';
    }
}

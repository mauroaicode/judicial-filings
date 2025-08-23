<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Channels;

use Core\BoundedContext\Customer\Process\Domain\Notification\NotificationChannelInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;
use Core\Shared\Domain\Enums\NotificationType;
use Illuminate\Support\Facades\Log;

readonly class WhatsAppNotificationChannel implements NotificationChannelInterface
{
    /**
     * Send notification through a WhatsApp channel
     */
    public function send(NotificationData $data): bool
    {
        try {
            $whatsappNumbers = $data->getOrganizationWhatsAppNumbers();

            if (empty($whatsappNumbers)) {
                Log::channel('notifications')->warning('No se encontraron canales de WhatsApp activos para enviar notificación', [
                    'type' => $data->getType(),
                    'process_id' => $data->getProcess()->id,
                    'organizations_count' => count($data->getOrganizations()),
                ]);
                return false;
            }

            // TODO: Implementar lógica de envío de WhatsApp
            // Por ahora solo simulamos el envío
            foreach ($whatsappNumbers as $number) {
                Log::channel('notifications')->info('Simulando envío de notificación por WhatsApp', [
                    'type' => $data->getType(),
                    'whatsapp_number' => $number,
                    'process_id' => $data->getProcess()->id,
                ]);
            }

            Log::channel('notifications')->info('Notificación enviada a todos los canales de WhatsApp activos', [
                'type' => $data->getType(),
                'whatsapp_numbers_count' => count($whatsappNumbers),
                'process_id' => $data->getProcess()->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::channel('notifications')->error('Error enviando notificación por WhatsApp: ' . $e->getMessage(), [
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
        return 'whatsapp';
    }
}

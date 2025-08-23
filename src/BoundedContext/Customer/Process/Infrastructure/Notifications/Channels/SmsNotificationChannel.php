<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Channels;

use Core\BoundedContext\Customer\Process\Domain\Notification\NotificationChannelInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;
use Illuminate\Support\Facades\Log;

readonly class SmsNotificationChannel implements NotificationChannelInterface
{
    /**
     * Send notification through an SMS channel
     */
    public function send(NotificationData $data): bool
    {
        try {
            $smsNumbers = $data->getOrganizationSmsNumbers();

            if (empty($smsNumbers)) {
                Log::channel('notifications')->warning('No se encontraron canales de SMS activos para enviar notificación', [
                    'type' => $data->getType(),
                    'process_id' => $data->getProcess()->id,
                    'organizations_count' => count($data->getOrganizations()),
                ]);
                return false;
            }

            // TODO: Implementar lógica de envío de SMS
            // Por ahora solo simulamos el envío
            foreach ($smsNumbers as $number) {
                Log::channel('notifications')->info('Simulando envío de notificación por SMS', [
                    'type' => $data->getType(),
                    'sms_number' => $number,
                    'process_id' => $data->getProcess()->id,
                ]);
            }

            Log::channel('notifications')->info('Notificación enviada a todos los canales de SMS activos', [
                'type' => $data->getType(),
                'sms_numbers_count' => count($smsNumbers),
                'process_id' => $data->getProcess()->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::channel('notifications')->error('Error enviando notificación por SMS: ' . $e->getMessage(), [
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
        return 'sms';
    }
}

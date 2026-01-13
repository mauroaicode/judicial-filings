<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Channels;

use Core\BoundedContext\Customer\Process\Domain\Notification\NotificationChannelInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Templates\EmailTemplate;
use Core\Shared\Domain\Enums\NotificationChannelType;
use Core\Shared\Domain\Enums\NotificationType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

readonly class EmailNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        private EmailTemplate $emailTemplate
    ) {}

    /**
     * Send notification through an email channel to a specific email address
     * NO hay try-catch aquí - todas las excepciones se propagan naturalmente
     * para que el job falle con el error real (SMTP, template, conexión, etc.)
     */
    public function send(NotificationData $data): bool
    {
        // Obtener el email específico del NotificationData
        $specificEmail = $data->getSpecificEmail();

        if (!$specificEmail) {
            Log::channel('notifications')->warning('No se encontró email específico para enviar notificación', [
                'type' => $data->getType(),
                'process_id' => $data->getProcess()->id,
            ]);
            return false;
        }

        // Obtener información de la organización
        $firstOrganization = $data->getOrganizations()[0] ?? null;
        $organizationId = null;

        if ($firstOrganization) {
            $organizationId = is_array($firstOrganization) ? $firstOrganization['id'] : $firstOrganization->id;
        }

        Log::channel('notifications')->info('Iniciando envío de email individual', [
            'type' => $data->getType(),
            'email' => $specificEmail,
            'organization_id' => $organizationId,
            'process_id' => $data->getProcess()->id,
        ]);

        $template = $this->getTemplateByType($data);

//        Mail::to($specificEmail)->send($template);

        Log::channel('notifications_email')->info('Email enviado exitosamente', [
            'type' => $data->getType(),
            'email' => $specificEmail,
            'organization_id' => $organizationId,
            'process_id' => $data->getProcess()->id,
            'data' => json_encode($data),
        ]);

        return true;
    }

    /**
     * Get channel name
     */
    public function getChannelName(): string
    {
        return NotificationChannelType::EMAIL->value;
    }

    /**
     * Get email template by notification type
     */
    private function getTemplateByType(NotificationData $data): mixed
    {
        return match($data->getType()) {
            NotificationType::MULTIPLE_INSTANCE->value => $this->emailTemplate->createMultipleInstancesTemplate($data),
            NotificationType::NEW_PROCESS_ACTION->value => $this->emailTemplate->createNewActionTemplate($data),
            NotificationType::AI_WORDS_PROCESS_ACTION->value => $this->emailTemplate->createAIAlertTemplate($data),
            default => throw new InvalidArgumentException("Tipo de notificación no soportado: {$data->getType()}"),
        };
    }
}

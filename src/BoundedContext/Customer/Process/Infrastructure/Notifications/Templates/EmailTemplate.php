<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Templates;

use Illuminate\Mail\{
    Mailable,
    Mailables\Content,
    Mailables\Envelope
};
use Core\Shared\Domain\Enums\NotificationType;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data\NotificationData;

class EmailTemplate extends Mailable
{
    private NotificationData $notificationData;

    public function __construct() {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getSubject(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->getView(),
            with: $this->getViewData(),
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        // No se requieren adjuntos para notificaciones de múltiples instancias
        return [];
    }

    /**
     * Create template for multiple instances notification
     */
    public function createMultipleInstancesTemplate(NotificationData $data): self
    {
        $this->notificationData = $data;
        return $this;
    }

    /**
     * Create template for new action notification
     */
    public function createNewActionTemplate(NotificationData $data): self
    {
        $this->notificationData = $data;
        return $this;
    }

    /**
     * Create template for AI alert notification
     */
    public function createAIAlertTemplate(NotificationData $data): self
    {
        $this->notificationData = $data;
        return $this;
    }

    /**
     * Get an email subject based on a notification type
     */
    private function getSubject(): string
    {
        return match($this->notificationData->getType()) {
            NotificationType::MULTIPLE_INSTANCE->value  => "📋 AVISO: Proceso con múltiples instancias - {$this->notificationData->getProcess()->process_number}",
            NotificationType::NEW_PROCESS_ACTION->value => "📋 Nueva actuación en proceso - {$this->notificationData->getProcess()->process_number}",
            NotificationType::AI_WORDS_PROCESS_ACTION->value => "⚠️ ALERTA CRÍTICA: Proceso requiere atención inmediata - {$this->notificationData->getProcess()->process_number}",
            default => "Notificación judicial - {$this->notificationData->getProcess()->process_number}",
        };
    }

    /**
     * Get email view based on a notification type
     */
    private function getView(): string
    {
        return match($this->notificationData->getType()) {
            NotificationType::MULTIPLE_INSTANCE->value => 'emails.notifications.multiple_instances',
            NotificationType::NEW_PROCESS_ACTION->value => 'emails.notifications.new_process_action',
            NotificationType::AI_WORDS_PROCESS_ACTION->value => 'emails.notifications.ai_words_process_action',
            default => 'emails.notifications.default',
        };
    }

    /**
     * Get data for the email view
     */
    private function getViewData(): array
    {
        return [
            'process' => $this->notificationData->getProcess(),
            'organizations' => $this->notificationData->getOrganizations(),
            'organizationNames' => $this->notificationData->getOrganizationNames(),
            'notificationType' => $this->notificationData->getType(),
            'additionalData' => $this->notificationData->getAdditionalData(),
        ];
    }
}

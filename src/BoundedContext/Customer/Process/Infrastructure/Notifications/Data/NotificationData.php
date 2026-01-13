<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Data;

use Core\Shared\Domain\Enums\NotificationChannelType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;

readonly class NotificationData
{
    public function __construct(
        public string $type,
        public array  $process,
        public array  $organizations,
        public array  $additionalData = [],
        public ?string $specificEmail = null // Email específico para este job
    )
    {
    }

    /**
     * Get a notification type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get a process
     */
    public function getProcess(): Process
    {
        // Si ya es un objeto Process, devolverlo directamente
        if ($this->process instanceof Process) {
            return $this->process;
        }
        
        // Si es un array, crear un nuevo objeto Process
        return new Process($this->process);
    }

    /**
     * Get process data as array
     */
    public function getProcessData(): array
    {
        return $this->process;
    }

    /**
     * Get process ID
     */
    public function getProcessId(): string
    {

        return $this->process['id'] ?? 'N/A';
    }

    /**
     * Get specific email for this notification
     */
    public function getSpecificEmail(): ?string
    {
        return $this->specificEmail;
    }

    /**
     * Get organizations
     */
    public function getOrganizations(): array
    {
        return $this->organizations;
    }

    /**
     * Get additional data
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    /**
     * Get organization emails from notification channels
     */
    public function getOrganizationEmails(): array
    {
        return collect($this->organizations)
            ->flatMap(function ($organization) {
                if (is_array($organization)) {
                    $org = Organization::query()
                        ->with('notificationChannels')
                        ->find($organization['id']);
                } else {
                    $org = $organization;
                }

                if (!$org) {
                    return [];
                }

                return $org->notificationChannels()
                    ->where('channel_type', NotificationChannelType::EMAIL)
                    ->where('is_active', true)
                    ->orderBy('priority')
                    ->pluck('channel_value')
                    ->toArray();
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get emails for a specific organization only
     */
    public function getEmailsForSpecificOrganization(string $organizationId): array
    {
        $organization = collect($this->organizations)
            ->first(function ($organization) use ($organizationId) {
                if (is_array($organization)) {
                    return $organization['id'] === $organizationId;
                }
                return $organization->id === $organizationId;
            });

        if (!$organization) {
            return [];
        }

        if (is_array($organization)) {
            $org = Organization::query()
                ->with('notificationChannels')
                ->find($organization['id']);
        } else {
            $org = $organization;
        }

        if (!$org) {
            return [];
        }

        return $org->notificationChannels()
            ->where('channel_type', NotificationChannelType::EMAIL)
            ->where('is_active', true)
            ->orderBy('priority')
            ->pluck('channel_value')
            ->toArray();
    }

    /**
     * Get organization names
     */
    public function getOrganizationNames(): array
    {
        return collect($this->organizations)
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get organization WhatsApp numbers from notification channels
     */
    public function getOrganizationWhatsAppNumbers(): array
    {
        return collect($this->organizations)
            ->flatMap(function ($organization) {
                if (is_array($organization)) {
                    $org = Organization::query()
                        ->with('notificationChannels')
                        ->find($organization['id']);
                } else {
                    $org = $organization;
                }

                if (!$org) {
                    return [];
                }

                return $org->notificationChannels()
                    ->where('channel_type', NotificationChannelType::WHATSAPP)
                    ->where('is_active', true)
                    ->orderBy('priority')
                    ->pluck('channel_value')
                    ->toArray();
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get organization SMS numbers from notification channels
     */
    public function getOrganizationSmsNumbers(): array
    {
        return collect($this->organizations)
            ->flatMap(function ($organization) {
                if (is_array($organization)) {
                    $org = Organization::query()
                        ->with('notificationChannels')
                        ->find($organization['id']);
                } else {
                    $org = $organization;
                }

                if (!$org) {
                    return [];
                }

                return $org->notificationChannels()
                    ->where('channel_type', NotificationChannelType::SMS)
                    ->where('is_active', true)
                    ->orderBy('priority')
                    ->pluck('channel_value')
                    ->toArray();
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get organization internal channels from notification channels
     */
    public function getOrganizationInternalChannels(): array
    {
        return collect($this->organizations)
            ->flatMap(function ($organization) {
                if (is_array($organization)) {
                    $org = Organization::query()
                        ->with('notificationChannels')
                        ->find($organization['id']);
                } else {
                    $org = $organization;
                }

                if (!$org) {
                    return [];
                }

                return $org->notificationChannels()
                    ->where('channel_type', NotificationChannelType::INTERNAL)
                    ->where('is_active', true)
                    ->orderBy('priority')
                    ->pluck('channel_value')
                    ->toArray();
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get all active notification channels for a specific type
     */
    public function getNotificationChannelsByType(NotificationChannelType $channelType): array
    {
        return collect($this->organizations)
            ->flatMap(function ($organization) use ($channelType) {
                if (is_array($organization)) {
                    $org = Organization::query()
                        ->with('notificationChannels')
                        ->find($organization['id']);
                } else {
                    $org = $organization;
                }

                if (!$org) {
                    return [];
                }

                return $org->notificationChannels()
                    ->where('channel_type', $channelType)
                    ->where('is_active', true)
                    ->orderBy('priority')
                    ->pluck('channel_value')
                    ->toArray();
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}

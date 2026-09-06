<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Models\Organization;

class OrganizationResource extends Resource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $type,
        public ?string $identification,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public ?string $contact_person,
        public bool $is_active,
        public ?string $created_at,
        public ?string $updated_at,
        public ?string $password = null,
        public bool $is_receiving_notifications = false,
        public ?int $max_active_processes = null,
        public int $active_processes_count = 0,
    ) {}

    public static function fromModel(Organization $organization): self
    {
        $isReceiving = (bool) ($organization->is_receiving_notifications ?? false);

        // Fallback for cases where it's not pre-loaded via withExists (e.g. after creation)
        if (! isset($organization->is_receiving_notifications) && $organization->relationLoaded('notificationChannels')) {
            $isReceiving = $organization->notificationChannels
                ->whereIn('channel_type', ['email', 'whatsapp', 'sms'])
                ->where('is_active', true)
                ->isNotEmpty();
        }

        /** @var OrganizationProcessQuotaService $quota */
        $quota = resolve(OrganizationProcessQuotaService::class);

        return new self(
            id: $organization->id,
            name: $organization->name,
            slug: $organization->slug,
            type: $organization->type,
            identification: $organization->identification,
            address: $organization->address,
            phone: $organization->phone,
            email: $organization->email,
            contact_person: $organization->contact_person,
            is_active: $organization->is_active,
            created_at: $organization->created_at->format('Y-m-d H:i:s'),
            updated_at: $organization->updated_at->format('Y-m-d H:i:s'),
            password: $organization->createdPassword ?? null,
            is_receiving_notifications: $isReceiving,
            max_active_processes: $quota->resolveLimit($organization->id),
            active_processes_count: $quota->countActiveProcesses($organization->id),
        );
    }
}

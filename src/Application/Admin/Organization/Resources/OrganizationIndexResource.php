<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Organization\Models\Organization;

class OrganizationIndexResource extends Resource
{
    public function __construct(
        public int $index,
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
        public bool $is_receiving_notifications = false,
    ) {}

    public static function fromModel(Organization $organization, int $index = 0): self
    {
        return new self(
            index: $index,
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
            is_receiving_notifications: (bool) ($organization->is_receiving_notifications ?? false),
        );
    }
}

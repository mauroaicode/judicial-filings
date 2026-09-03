<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Enums\OrganizationType;
use Src\Domain\Organization\Models\Organization;

/**
 * Admin organization detail for modal/tabs (info + settings).
 */
class OrganizationDetailResource extends Resource
{
    /**
     * @param  array{
     *     max_active_processes: int|null,
     *     max_active_processes_configured: int|null,
     *     default_max_active_processes: int|null,
     *     remaining_slots: int|null
     * }  $settings
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $type,
        public string $type_label,
        public ?string $identification,
        public ?string $email,
        public ?string $phone,
        public ?string $contact_person,
        public bool $is_active,
        public int $active_processes_count,
        public array $settings,
    ) {}

    public static function fromModel(
        Organization $organization,
        OrganizationProcessQuotaService $quotaService,
    ): self {
        $typeEnum = OrganizationType::tryFrom($organization->type);

        return new self(
            id: $organization->id,
            name: $organization->name,
            slug: $organization->slug,
            type: $organization->type,
            type_label: $typeEnum?->getLabel() ?? $organization->type,
            identification: $organization->identification,
            email: $organization->email,
            phone: $organization->phone,
            contact_person: $organization->contact_person,
            is_active: $organization->is_active,
            active_processes_count: $quotaService->countActiveProcesses($organization->id),
            settings: [
                'max_active_processes' => $quotaService->resolveLimit($organization->id),
                'max_active_processes_configured' => $quotaService->configuredMaxActiveProcesses($organization->id),
                'default_max_active_processes' => $quotaService->defaultMaxActiveProcesses(),
                'remaining_slots' => $quotaService->remainingSlots($organization->id),
            ],
        );
    }
}

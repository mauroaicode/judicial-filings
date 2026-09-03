<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Models\Organization;

class OrganizationSettingsResource extends Resource
{
    public function __construct(
        public string $organization_id,
        /** Effective limit after applying .env default. null = unlimited. */
        public ?int $max_active_processes,
        /** Raw DB value. null = not configured → uses default. */
        public ?int $max_active_processes_configured,
        /** Global default from ORGANIZATION_DEFAULT_MAX_ACTIVE_PROCESSES. */
        public ?int $default_max_active_processes,
        public int $active_processes_count,
        public ?int $remaining_slots,
    ) {}

    public static function fromOrganization(
        Organization $organization,
        OrganizationProcessQuotaService $quotaService,
    ): self {
        return new self(
            organization_id: $organization->id,
            max_active_processes: $quotaService->resolveLimit($organization->id),
            max_active_processes_configured: $quotaService->configuredMaxActiveProcesses($organization->id),
            default_max_active_processes: $quotaService->defaultMaxActiveProcesses(),
            active_processes_count: $quotaService->countActiveProcesses($organization->id),
            remaining_slots: $quotaService->remainingSlots($organization->id),
        );
    }
}

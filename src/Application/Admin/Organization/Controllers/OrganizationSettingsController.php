<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Organization\Data\UpdateOrganizationSettingsData;
use Src\Application\Admin\Organization\Resources\OrganizationSettingsResource;
use Src\Application\Admin\Organization\Services\UpdateOrganizationSettingsService;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Models\Organization;

readonly class OrganizationSettingsController
{
    public function __construct(
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
        private UpdateOrganizationSettingsService $updateOrganizationSettingsService,
    ) {}

    public function show(Organization $organization): OrganizationSettingsResource
    {
        $this->organizationProcessQuotaService->ensureSettings($organization);

        return OrganizationSettingsResource::fromOrganization(
            $organization->fresh(['settings']) ?? $organization,
            $this->organizationProcessQuotaService,
        );
    }

    public function update(
        Organization $organization,
        UpdateOrganizationSettingsData $data,
    ): JsonResponse {
        $this->updateOrganizationSettingsService->handle($organization, $data);

        return response()->json([
            'message' => __('organization.settings_updated'),
            'settings' => OrganizationSettingsResource::fromOrganization(
                $organization->fresh(['settings']) ?? $organization,
                $this->organizationProcessQuotaService,
            )->toArray(),
        ]);
    }
}

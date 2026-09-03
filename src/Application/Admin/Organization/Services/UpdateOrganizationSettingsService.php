<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Src\Application\Admin\Organization\Data\UpdateOrganizationSettingsData;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;

readonly class UpdateOrganizationSettingsService
{
    public function __construct(
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

    public function handle(Organization $organization, UpdateOrganizationSettingsData $data): OrganizationSetting
    {
        $settings = $this->organizationProcessQuotaService->ensureSettings($organization);

        $settings->update([
            'max_active_processes' => $data->max_active_processes,
        ]);

        return $settings->refresh();
    }
}

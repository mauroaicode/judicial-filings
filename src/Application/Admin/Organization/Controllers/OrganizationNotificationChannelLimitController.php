<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Organization\Data\UpdateOrganizationNotificationChannelLimitsData;
use Src\Application\Admin\Organization\Services\UpdateOrganizationNotificationChannelLimitsService;
use Src\Application\Shared\Notification\Resources\OrganizationNotificationChannelLimitResource;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelQuotaService;
use Src\Domain\Organization\Models\Organization;

readonly class OrganizationNotificationChannelLimitController
{
    public function __construct(
        private OrganizationNotificationChannelQuotaService $quotaService,
        private UpdateOrganizationNotificationChannelLimitsService $updateOrganizationNotificationChannelLimitsService,
    ) {}

    public function show(Organization $organization): JsonResponse
    {
        return response()->json($this->payload($organization));
    }

    public function update(
        Organization $organization,
        UpdateOrganizationNotificationChannelLimitsData $data,
    ): JsonResponse {
        $this->updateOrganizationNotificationChannelLimitsService->handle($organization, $data);

        return response()->json([
            'message' => __('organization.notification_channel_limits_updated'),
            'limits' => $this->payload($organization),
        ]);
    }

    /**
     * @return array{organization_id: string, default_limit: int, channels: list<array<string, mixed>>}
     */
    private function payload(Organization $organization): array
    {
        return [
            'organization_id' => $organization->id,
            'default_limit' => $this->quotaService->defaultLimit(),
            'channels' => array_map(
                fn (array $row): array => OrganizationNotificationChannelLimitResource::fromSummary($row)->toArray(),
                $this->quotaService->summarize($organization->id),
            ),
        ];
    }
}

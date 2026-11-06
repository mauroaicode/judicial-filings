<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Config\Data\StoreOrganizationNotificationChannelData;
use Src\Application\AppUser\Config\Data\UpdateOrganizationNotificationChannelData;
use Src\Application\AppUser\Config\Services\DeleteOrganizationNotificationChannelService;
use Src\Application\AppUser\Config\Services\StoreOrganizationNotificationChannelService;
use Src\Application\AppUser\Config\Services\UpdateOrganizationNotificationChannelService;
use Src\Application\Shared\Notification\Resources\OrganizationNotificationChannelLimitResource;
use Src\Application\Shared\Notification\Resources\OrganizationNotificationChannelResource;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelEmailVerificationService;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelQuotaService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Organization\Models\Organization;

readonly class OrganizationNotificationChannelController
{
    public function __construct(
        private OrganizationNotificationChannelQuotaService $quotaService,
        private StoreOrganizationNotificationChannelService $storeOrganizationNotificationChannelService,
        private UpdateOrganizationNotificationChannelService $updateOrganizationNotificationChannelService,
        private DeleteOrganizationNotificationChannelService $deleteOrganizationNotificationChannelService,
        private OrganizationNotificationChannelEmailVerificationService $emailVerificationService,
    ) {}

    public function index(): JsonResponse
    {
        $organization = $this->organization();

        $channels = OrganizationNotificationChannel::query()
            ->where('organization_id', $organization->id)
            ->whereIn('channel_type', $this->quotaService->configurableTypes())
            ->orderBy('channel_type')
            ->orderBy('priority')
            ->get()
            ->map(fn (OrganizationNotificationChannel $channel): array => OrganizationNotificationChannelResource::fromModel($channel)->toArray())
            ->values();

        return response()->json([
            'channels' => $channels,
            'limits' => $this->limitsPayload($organization),
        ]);
    }

    public function limits(): JsonResponse
    {
        return response()->json($this->limitsPayload($this->organization()));
    }

    public function store(StoreOrganizationNotificationChannelData $data): JsonResponse
    {
        $channel = $this->storeOrganizationNotificationChannelService->handle($data, $this->organization()->id);

        return response()->json(
            OrganizationNotificationChannelResource::fromModel($channel)->toArray(),
            201,
        );
    }

    public function update(
        OrganizationNotificationChannel $notificationChannel,
        UpdateOrganizationNotificationChannelData $data,
    ): JsonResponse {
        $channel = $this->channelForOrganization($notificationChannel);
        $channel = $this->updateOrganizationNotificationChannelService->handle($channel, $data);

        return response()->json(OrganizationNotificationChannelResource::fromModel($channel)->toArray());
    }

    public function resendVerification(OrganizationNotificationChannel $notificationChannel): JsonResponse
    {
        $channel = $this->channelForOrganization($notificationChannel);
        $this->emailVerificationService->resend($channel);

        return response()->json(OrganizationNotificationChannelResource::fromModel($channel->refresh())->toArray());
    }

    public function destroy(OrganizationNotificationChannel $notificationChannel): JsonResponse
    {
        $channel = $this->channelForOrganization($notificationChannel);
        $this->deleteOrganizationNotificationChannelService->handle($channel);

        return response()->json(null, 204);
    }

    private function organization(): Organization
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization instanceof Organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        return $organization;
    }

    private function channelForOrganization(OrganizationNotificationChannel $channel): OrganizationNotificationChannel
    {
        $organization = $this->organization();

        if (
            $channel->organization_id !== $organization->id
            || ! in_array($channel->channel_type, $this->quotaService->configurableTypes(), true)
        ) {
            abort(404, __('organization.notification_channel_not_found'));
        }

        return $channel;
    }

    /**
     * @return array{default_limit: int, channels: list<array<string, mixed>>}
     */
    private function limitsPayload(Organization $organization): array
    {
        return [
            'default_limit' => $this->quotaService->defaultLimit(),
            'channels' => array_map(
                fn (array $row): array => OrganizationNotificationChannelLimitResource::fromSummary($row)->toArray(),
                $this->quotaService->summarize($organization->id),
            ),
        ];
    }
}

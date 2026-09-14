<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Src\Application\Shared\Services\Notification\Channels\ManualRegistrationDiscordNotificationService;
use Src\Application\Shared\Services\Notification\NotifyAdminsManualRegistrationRequestService;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Process\Models\UnassignedProcessAction;

/**
 * Persists a pending manual registration request and notifies ops (Discord + admin WebSocket).
 */
readonly class RequestManualProcessRegistrationService
{
    public function __construct(
        private ManualRegistrationDiscordNotificationService $discordNotificationService,
        private NotifyAdminsManualRegistrationRequestService $notifyAdminsService,
    ) {}

    public function handle(
        string $processNumber,
        string $organizationId,
        string $appUserId,
        ManualRegistrationRequestReason $reason,
        ?ProcessLawyerRole $lawyerRole = null,
    ): ManualRegistrationRequest {
        $existing = ManualRegistrationRequest::query()
            ->where('organization_id', $organizationId)
            ->where('process_number', $processNumber)
            ->where('status', ManualRegistrationRequestStatus::Pending->value)
            ->latest()
            ->first();

        if ($existing instanceof ManualRegistrationRequest) {
            return $existing;
        }

        $unassignedCount = UnassignedProcessAction::query()
            ->whereProcessNumber($processNumber)
            ->whereUnassigned()
            ->count();

        $request = ManualRegistrationRequest::query()->create([
            'organization_id' => $organizationId,
            'app_user_id' => $appUserId,
            'process_number' => $processNumber,
            'reason' => $reason,
            'status' => ManualRegistrationRequestStatus::Pending,
            'lawyer_role' => $lawyerRole,
            'unassigned_actions_count' => $unassignedCount,
            'discord_notified' => false,
        ]);

        $notified = $this->discordNotificationService->notify($request);

        if ($notified) {
            $request->update(['discord_notified' => true]);
        }

        $this->notifyAdminsService->handle($request);

        return $request->fresh() ?? $request;
    }
}

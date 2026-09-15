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

    /**
     * @param  array{
     *     process_class?: string|null,
     *     plaintiffs?: list<array{name: string, identification: string|null}>|null,
     *     defendants?: list<array{name: string, identification: string|null}>|null,
     *     other_subjects?: list<array{name: string, identification: string|null}>|null,
     * }|null  $details
     */
    public function handle(
        string $processNumber,
        string $organizationId,
        string $appUserId,
        ManualRegistrationRequestReason $reason,
        ?ProcessLawyerRole $lawyerRole = null,
        ?array $details = null,
    ): ManualRegistrationRequest {
        $existing = ManualRegistrationRequest::query()
            ->where('organization_id', $organizationId)
            ->where('process_number', $processNumber)
            ->where('status', ManualRegistrationRequestStatus::Pending->value)
            ->latest()
            ->first();

        if ($existing instanceof ManualRegistrationRequest) {
            if ($details !== null) {
                $existing->update([
                    'lawyer_role' => $lawyerRole ?? $existing->lawyer_role,
                    'reason' => $reason,
                    'process_class' => $details['process_class'] ?? $existing->process_class,
                    'plaintiffs' => $details['plaintiffs'] ?? $existing->plaintiffs,
                    'defendants' => $details['defendants'] ?? $existing->defendants,
                    'other_subjects' => $details['other_subjects'] ?? $existing->other_subjects,
                ]);

                $existing = $existing->fresh(['appUser', 'organization']) ?? $existing;

                if (! $existing->discord_notified) {
                    $notified = $this->discordNotificationService->notify($existing);
                    if ($notified) {
                        $existing->update(['discord_notified' => true]);
                    }
                    $this->notifyAdminsService->handle($existing);
                }
            }

            return $existing->fresh() ?? $existing;
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
            'process_class' => $details['process_class'] ?? null,
            'plaintiffs' => $details['plaintiffs'] ?? null,
            'defendants' => $details['defendants'] ?? null,
            'other_subjects' => $details['other_subjects'] ?? null,
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

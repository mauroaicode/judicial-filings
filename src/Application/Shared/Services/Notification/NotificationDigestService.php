<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable;
use Src\Application\Shared\Notifications\ConsolidatedJudicialActionsNotification;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;

class NotificationDigestService
{
    public function __construct(
        private readonly CollectPendingDigestNotificationsService $collectPendingDigestNotificationsService,
        private readonly BuildNotificationDigestRowsService $buildNotificationDigestRowsService,
        private readonly AlreadySentDigestActuacionChecker $alreadySentDigestActuacionChecker,
    ) {}

    public function sendDigest(
        Organization $organization,
        ?array $limitToProcessNumbers = null,
        bool $skipRegistrationCutoff = false,
    ): void {
        if (! $organization->canTrackProcesses()) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('NotificationDigestService: Skipping digest for organization without process tracking', [
                    'organization_id' => $organization->id,
                ]);

            return;
        }

        [$notifications, $alreadySentDuplicates] = $this->collectPendingDigestNotificationsService
            ->collectPartitioned($organization, $limitToProcessNumbers, $skipRegistrationCutoff);

        if ($alreadySentDuplicates->isNotEmpty()) {
            $this->alreadySentDigestActuacionChecker->markSuppressedAsNotified($alreadySentDuplicates);
        }

        if ($notifications->isEmpty()) {
            return;
        }

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('NotificationDigestService: Preparing digest', [
                'organization_id' => $organization->id,
                'count' => $notifications->count(),
                'suppressed_already_sent' => $alreadySentDuplicates->count(),
            ]);

        $digestData = $this->buildNotificationDigestRowsService->handle($notifications, $organization->id);

        if ($digestData->isEmpty()) {
            return;
        }

        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        // Persist the digest record BEFORE sending — gives it an ID for the frontend
        // and links notifications regardless of whether delivery channels are active.
        $digest = NotificationDigest::query()->create([
            'organization_id' => $organization->id,
            'data' => $digestData->all(),
            // Channel timestamps are set individually AFTER each successful send.
        ]);

        // Mark notifications as notified and link them to the digest.
        // This prevents double-sending if a channel later fails and we retry.
        $this->markAsNotified($notifications, $digest->id);

        // Send the consolidated email to every active email channel (priority order).
        $emailChannels = $organization->notificationChannels()
            ->where('channel_type', 'email')
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        $emailRecipients = $emailChannels
            ->pluck('channel_value')
            ->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => trim($value))
            ->unique()
            ->values();

        if ($emailRecipients->isEmpty()) {
            Log::channel($logChannel)->info('NotificationDigestService: Skipping email channel (inactive or missing)', [
                'organization_id' => $organization->id,
                'digest_id' => $digest->id,
            ]);
        } else {
            foreach ($emailRecipients as $recipientEmail) {
                $this->sendEmailChannel($digest, $digestData, $recipientEmail, $organization->id, $organization->name);
            }
        }

        // Send internal notification (Bell/Websocket) only when the internal channel is active.
        $internalChannelActive = $organization->notificationChannels()
            ->where('channel_type', 'internal')
            ->where('is_active', true)
            ->exists();

        $users = $organization->appUsers;
        if ($internalChannelActive && $users->isNotEmpty()) {
            $actionsCount = $digestData->where('is_alert', false)->count();
            $alertsCount = $digestData->where('is_alert', true)->count();

            try {
                Notification::send(
                    $users,
                    new ConsolidatedJudicialActionsNotification($digest, $actionsCount, $alertsCount)
                );
            } catch (\Throwable $e) {
                Log::channel($logChannel)
                    ->error('NotificationDigestService: Failed to send internal notification', [
                        'organization_id' => $organization->id,
                        'digest_id' => $digest->id,
                        'message' => $e->getMessage(),
                    ]);
            }
        } elseif (! $internalChannelActive) {
            Log::channel($logChannel)->info('NotificationDigestService: Skipping internal channel (inactive)', [
                'organization_id' => $organization->id,
                'digest_id' => $digest->id,
            ]);
        }
    }

    /**
     * Attempts to send the email channel and records email_sent_at on success.
     * Failures are logged but do NOT bubble up — other channels can still proceed.
     */
    private function sendEmailChannel(
        NotificationDigest $digest,
        Collection $digestData,
        string $recipientEmail,
        string $organizationId,
        string $organizationName
    ): void {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        try {
            Mail::to($recipientEmail)->send(
                new ConsolidatedJudicialActionsMailable(
                    $digestData,
                    StrParseHelper::toTitleCase($organizationName),
                    $digest->id,
                )
            );

            // Only mark as sent if Mail::send() did not throw
            $digest->update(['email_sent_at' => now()]);

            Log::channel($logChannel)->info('NotificationDigestService: Email sent successfully', [
                'organization_id' => $organizationId,
                'digest_id' => $digest->id,
                'recipient' => $recipientEmail,
            ]);

        } catch (\Throwable $e) {
            Log::channel($logChannel)->error('NotificationDigestService: Failed to send email channel', [
                'organization_id' => $organizationId,
                'digest_id' => $digest->id,
                'recipient' => $recipientEmail,
                'message' => $e->getMessage(),
            ]);
            // email_sent_at remains null — queryable to detect and retry failed sends.
        }
    }

    private function markAsNotified(Collection $notifications, string $digestId): void
    {
        OrganizationNotification::query()
            ->whereIn('id', $notifications->pluck('id'))
            ->update([
                'is_email_notified' => true,
                'email_notified_at' => now(),
                'is_notified' => true,
                'notified_at' => now(),
                'notification_digest_id' => $digestId,
            ]);
    }
}

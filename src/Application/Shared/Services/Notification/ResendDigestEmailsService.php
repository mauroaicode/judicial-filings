<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;

readonly class ResendDigestEmailsResult
{
    /**
     * @param  list<string>  $sentTo
     * @param  list<string>  $skipped
     * @param  list<string>  $failed
     */
    public function __construct(
        public ?string $digestId,
        public ?string $digestCreatedAt,
        public array $sentTo,
        public array $skipped,
        public array $failed,
    ) {}
}

readonly class ResendDigestEmailsService
{
    /**
     * Resend an existing digest mailable to active email channels.
     *
     * Default target is secondary emails (priority > 1), because the legacy digest
     * sender only delivered to the first active email channel.
     *
     * @param  list<string>|null  $onlyEmails  When set, restrict to these addresses
     */
    public function resend(
        string $organizationId,
        ?string $digestId = null,
        ?string $onDate = null,
        bool $includePrimary = false,
        ?array $onlyEmails = null,
        bool $dryRun = false,
    ): ResendDigestEmailsResult {
        $organization = Organization::query()->findOrFail($organizationId);
        $digest = $this->resolveDigest($organizationId, $digestId, $onDate);

        if (! $digest instanceof \Src\Domain\Notification\Models\NotificationDigest) {
            return new ResendDigestEmailsResult(
                digestId: null,
                digestCreatedAt: null,
                sentTo: [],
                skipped: ['digest_not_found'],
                failed: [],
            );
        }

        $digestData = collect($digest->data ?? []);
        if ($digestData->isEmpty()) {
            return new ResendDigestEmailsResult(
                digestId: $digest->id,
                digestCreatedAt: $digest->created_at->toDateTimeString(),
                sentTo: [],
                skipped: ['digest_data_empty'],
                failed: [],
            );
        }

        $recipients = $this->resolveRecipients($organization, $includePrimary, $onlyEmails);

        if ($recipients === []) {
            return new ResendDigestEmailsResult(
                digestId: $digest->id,
                digestCreatedAt: $digest->created_at->toDateTimeString(),
                sentTo: [],
                skipped: ['no_matching_email_channels'],
                failed: [],
            );
        }

        if ($dryRun) {
            return new ResendDigestEmailsResult(
                digestId: $digest->id,
                digestCreatedAt: $digest->created_at->toDateTimeString(),
                sentTo: [],
                skipped: array_map(static fn (string $email): string => "dry_run:{$email}", $recipients),
                failed: [],
            );
        }

        $sentTo = [];
        $failed = [];
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        foreach ($recipients as $recipientEmail) {
            try {
                Mail::to($recipientEmail)->send(
                    new ConsolidatedJudicialActionsMailable(
                        $digestData,
                        StrParseHelper::toTitleCase($organization->name) ?? $organization->name,
                        $digest->id,
                    )
                );

                $sentTo[] = $recipientEmail;

                Log::channel($logChannel)->info('ResendDigestEmailsService: Email resent successfully', [
                    'organization_id' => $organizationId,
                    'digest_id' => $digest->id,
                    'recipient' => $recipientEmail,
                ]);
            } catch (\Throwable $e) {
                $failed[] = $recipientEmail.': '.$e->getMessage();

                Log::channel($logChannel)->error('ResendDigestEmailsService: Failed to resend email', [
                    'organization_id' => $organizationId,
                    'digest_id' => $digest->id,
                    'recipient' => $recipientEmail,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($sentTo !== [] && $digest->email_sent_at === null) {
            $digest->update(['email_sent_at' => now()]);
        }

        return new ResendDigestEmailsResult(
            digestId: $digest->id,
            digestCreatedAt: $digest->created_at->toDateTimeString(),
            sentTo: $sentTo,
            skipped: [],
            failed: $failed,
        );
    }

    private function resolveDigest(string $organizationId, ?string $digestId, ?string $onDate): ?NotificationDigest
    {
        if ($digestId !== null && $digestId !== '') {
            return NotificationDigest::query()
                ->where('organization_id', $organizationId)
                ->where('id', $digestId)
                ->first();
        }

        $query = NotificationDigest::query()
            ->where('organization_id', $organizationId)->latest();

        if ($onDate !== null && $onDate !== '') {
            $query->whereDate('created_at', $onDate);
        } else {
            $query->whereDate('created_at', today());
        }

        return $query->first();
    }

    /**
     * @param  list<string>|null  $onlyEmails
     * @return list<string>
     */
    private function resolveRecipients(
        Organization $organization,
        bool $includePrimary,
        ?array $onlyEmails,
    ): array {
        $channels = $organization->notificationChannels()
            ->where('channel_type', 'email')
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        if (! $includePrimary) {
            $minPriority = $channels->min('priority');
            $channels = $channels->filter(
                static fn ($channel): bool => (int) $channel->priority > (int) $minPriority,
            );
        }

        $emails = $channels
            ->pluck('channel_value')
            ->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => mb_strtolower(trim($value)))
            ->unique()
            ->values();

        if ($onlyEmails !== null && $onlyEmails !== []) {
            $allowed = collect($onlyEmails)
                ->map(static fn (string $email): string => mb_strtolower(trim($email)))
                ->all();
            $emails = $emails->filter(static fn (string $email): bool => in_array($email, $allowed, true))->values();
        }

        return $emails->all();
    }
}

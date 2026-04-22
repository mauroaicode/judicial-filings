<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable;
use Src\Application\Shared\Notifications\ConsolidatedJudicialActionsNotification;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;
use Src\Domain\Process\Services\GroupProcessActionsService;

class NotificationDigestService
{
    public function __construct(
        private readonly GroupProcessActionsService $groupProcessActionsService
    ) {}

    public function sendDigest(Organization $organization): void
    {
        // 1. Get all pending email notifications for this organization
        $notifications = $organization->notifications()
            ->where('is_email_notified', false)
            ->where('notifiable_type', (new ProcessAction)->getMorphClass())
            ->orderedByNotifiableRegistrationDateDesc()
            ->with(['notifiable.process'])
            ->get();

        if ($notifications->isEmpty()) {
            return;
        }

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('NotificationDigestService: Preparing digest', [
                'organization_id' => $organization->id,
                'count' => $notifications->count(),
            ]);

        // 2. Prepare structured data for the table
        $digestData = $this->prepareData($notifications, $organization->id);

        // 3. Link and Merge Fijaciones with Autos
        $digestData = $this->groupAndMergeActions($digestData);

        if ($digestData->isEmpty()) {
            return;
        }

        // 3. Find the organization's email (from channel or explicitly)
        $emailChannel = $organization->notificationChannels()
            ->where('channel_type', 'email')
            ->where('is_active', true)
            ->first();

        if (! $emailChannel || empty($emailChannel->channel_value)) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->warning('NotificationDigestService: No active email channel found', [
                    'organization_id' => $organization->id,
                ]);

            return;
        }

        // 4. Persist the digest record BEFORE sending — gives it an ID for the frontend
        //    and links notifications regardless of whether channels succeed.
        $digest = NotificationDigest::query()->create([
            'organization_id' => $organization->id,
            'data'            => $digestData->toArray(),
            // Channel timestamps are set individually AFTER each successful send.
        ]);

        // 5. Mark notifications as notified and link them to the digest.
        //    This prevents double-sending if a channel later fails and we retry.
        $this->markAsNotified($notifications, $digest->id);

        // 6. Send the consolidated email and record the timestamp only on success.
        $this->sendEmailChannel($digest, $digestData, $emailChannel->channel_value, $organization->id, $organization->name);

        // 7. Send internal notification (Bell/Websocket) — only ONE per digest.
        $users = $organization->appUsers;
        if ($users->isNotEmpty()) {
            $actionsCount = $digestData->where('is_alert', false)->count();
            $alertsCount  = $digestData->where('is_alert', true)->count();

            try {
                Notification::send(
                    $users,
                    new ConsolidatedJudicialActionsNotification($digest, $actionsCount, $alertsCount)
                );
            } catch (\Throwable $e) {
                Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                    ->error('NotificationDigestService: Failed to send internal notification', [
                        'organization_id' => $organization->id,
                        'digest_id'       => $digest->id,
                        'message'         => $e->getMessage(),
                    ]);
            }
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
                new ConsolidatedJudicialActionsMailable($digestData, StrParseHelper::toTitleCase($organizationName))
            );

            // Only mark as sent if Mail::send() did not throw
            $digest->update(['email_sent_at' => now()]);

            Log::channel($logChannel)->info('NotificationDigestService: Email sent successfully', [
                'organization_id' => $organizationId,
                'digest_id'       => $digest->id,
                'recipient'       => $recipientEmail,
            ]);

        } catch (\Throwable $e) {
            Log::channel($logChannel)->error('NotificationDigestService: Failed to send email channel', [
                'organization_id' => $organizationId,
                'digest_id'       => $digest->id,
                'recipient'       => $recipientEmail,
                'message'         => $e->getMessage(),
            ]);
            // email_sent_at remains null — queryable to detect and retry failed sends.
        }
    }

    private function prepareData(Collection $notifications, string $organizationId): Collection
    {
        return $notifications->groupBy('notifiable_id')->map(function (Collection $group) use ($organizationId): ?array {
            /** @var OrganizationNotification $notif */
            $notif = $group->first();
            $action = $notif->notifiable;

            if (! $action instanceof ProcessAction) {
                return null;
            }

            $process = $action->process;

            // Extract Parties (Demandante/Demandado) - explicitly for this process
            $parties = $process->subjects()
                ->select(['subject_type', 'name_or_business_name'])
                ->get();

            $demandanteNames = $parties->where('subject_type', 'Demandante')->pluck('name_or_business_name')->unique()->sort()->values();
            $demandante = $demandanteNames->map(fn (?string $name): ?string => StrParseHelper::toTitleCase($name))->implode(', ');

            $demandadoNames = $parties->where('subject_type', 'Demandado')->pluck('name_or_business_name')->unique()->sort()->values();
            $demandado = $demandadoNames->map(fn (?string $name): ?string => StrParseHelper::toTitleCase($name))->implode(', ');

            // Check if any notification in this group is an alert
            $isAlert = $group->contains('notification_type', 'actuacion_alerta');
            $matchedKeywords = null;
            $alertHighlights = [];

            if ($isAlert) {
                $highlights = ProcessActionAlertHighlight::query()
                    ->where('process_action_id', $action->id)
                    ->where('organization_id', $organizationId)
                    ->get()
                    ->unique(fn ($h): string => "{$h->start}-{$h->end}-{$h->detected_text}-{$h->source}");

                $matchedKeywords = $highlights->pluck('detected_text')->unique()->implode(', ');

                $alertHighlights = $highlights->map(fn ($h): array => [
                    'start' => $h->start,
                    'end' => $h->end,
                    'text' => $h->detected_text,
                    'source' => $h->source,
                ])->all();
            }

            return [
                'process_action_id' => $action->id,
                'court' => StrParseHelper::toTitleCase($process->court),
                'process_number' => $process->process_number,
                'demandante' => empty($demandante) ? '---' : $demandante,
                'demandado' => empty($demandado) ? '---' : $demandado,
                'action_date' => DateFormatHelper::formatDate($action->action_date),
                'action_text' => StrParseHelper::toTitleCase((string) $action->action),
                'annotation' => StrParseHelper::toTitleCase((string) ($action->annotation ?: '---')),
                'term_start_date' => $action->start_date ? DateFormatHelper::formatDate($action->start_date) : null,
                'term_end_date' => $action->end_date ? DateFormatHelper::formatDate($action->end_date) : null,
                'registration_date' => DateFormatHelper::formatDate($action->registration_date),
                'is_alert' => $isAlert,
                'matched_keywords' => $matchedKeywords,
                'alert_highlights' => $alertHighlights,
            ];
        })->filter()->values();
    }

    /**
     * Groups related actions (like Fijación + Auto) into a single row data if they exist in the same digest.
     */
    private function groupAndMergeActions(Collection $data): Collection
    {
        // 1. Tag pairs using the existing service
        $tagged = $this->groupProcessActionsService->handle($data);

        $toRemove = collect();
        $merged = $tagged->map(function (array $item) use ($tagged, $toRemove): array {
            // If it's an Auto that was already 'claimed' by a Fijación, we ignore it here (as it will be removed later)
            // Check if the Fijación actually exists in this collection
            if (isset($item['fijacion_action_id']) && $tagged->contains('process_action_id', $item['fijacion_action_id'])) {
                $toRemove->push($item['process_action_id'] ?? $item['id']);
            }

            // If it's a Fijación with a linked Auto, we merge the Auto's text into this one
            if (isset($item['notified_action_id'])) {
                $auto = $tagged->firstWhere('process_action_id', $item['notified_action_id']);

                if ($auto) {
                    $item['is_merged'] = true;
                    // Store the 'Pair' info for the Blade template
                    $item['linked_action_text'] = $auto['action_text'];
                    $item['linked_annotation'] = $auto['annotation'];
                    // Merge alert status: if any of the two is an alert, the whole row is an alert
                    $item['is_alert'] = $item['is_alert'] || $auto['is_alert'];
                    $item['matched_keywords'] = collect([$item['matched_keywords'], $auto['matched_keywords']])
                        ->filter()
                        ->unique()
                        ->implode(', ');
                }
            }

            return $item;
        });

        // 2. Remove the "independent" rows of Autos that are now part of a Fijación row
        return $merged->reject(fn (array $item) => $toRemove->contains($item['process_action_id'] ?? $item['id']))->values();
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

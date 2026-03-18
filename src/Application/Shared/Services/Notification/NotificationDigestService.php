<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable;
use Src\Application\Shared\Notifications\ConsolidatedJudicialActionsNotification;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

class NotificationDigestService
{
    public function sendDigest(Organization $organization): void
    {
        // 1. Get all pending email notifications for this organization
        $notifications = $organization->notifications()
            ->where('is_email_notified', false)
            ->where('notifiable_type', (new ProcessAction())->getMorphClass())
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

        if ($digestData->isEmpty()) {
            return;
        }

        // 3. Find the organization's email (from channel or explicitly)
        $emailChannel = $organization->notificationChannels()
            ->where('channel_type', 'email')
            ->where('is_active', true)
            ->first();

        if (!$emailChannel || empty($emailChannel->channel_value)) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->warning('NotificationDigestService: No active email channel found', [
                    'organization_id' => $organization->id
                ]);
            return;
        }

        // 4. Send the consolidated email
        try {
            // Persist the digest to DB first to have an ID for the frontend
            $digest = NotificationDigest::create([
                'organization_id' => $organization->id,
                'data' => $digestData->toArray(),
                'email_sent_at' => now(),
            ]);

            Mail::to($emailChannel->channel_value)->send(
                new ConsolidatedJudicialActionsMailable($digestData, $organization->name)
            );

            // 5. Mark as notified and link to digest in DB
            $this->markAsNotified($notifications, $digest->id);

            // 6. Send internal notification (Bell/Websocket) - only ONE
            $users = $organization->appUsers;
            if ($users->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send(
                    $users,
                    new ConsolidatedJudicialActionsNotification($digest, $digestData->count())
                );
            }

            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->info('NotificationDigestService: Consolidated email sent', [
                    'organization_id' => $organization->id,
                    'recipient' => $emailChannel->channel_value,
                ]);

        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('NotificationDigestService: Failed to send digest email', [
                    'organization_id' => $organization->id,
                    'message' => $e->getMessage(),
                ]);
        }
    }

    private function prepareData(Collection $notifications, string $organizationId): Collection
    {
        return $notifications->map(function (OrganizationNotification $notif) use ($organizationId) {
            $action = $notif->notifiable;
            if (!$action instanceof ProcessAction) {
                return null;
            }

            $process = $action->process;
            
            // Extract Parties (Demandante/Demandado) - explicitly for this process
            $parties = $process->subjects()
                ->select(['subject_type', 'name_or_business_name'])
                ->get();
            
            $demandante = $parties->where('subject_type', 'Demandante')->pluck('name_or_business_name')->unique()->implode(', ');
            $demandado = $parties->where('subject_type', 'Demandado')->pluck('name_or_business_name')->unique()->implode(', ');

            // Check for keywords if it's an alert
            $isAlert = $notif->notification_type === 'actuacion_alerta';
            $matchedKeywords = null;
            if ($isAlert) {
                $matchedKeywords = ProcessActionAlertHighlight::query()
                    ->where('process_action_id', $action->id)
                    ->where('organization_id', $organizationId)
                    ->pluck('detected_text')
                    ->unique()
                    ->implode(', ');
            }

            return [
                'court' => $process->court,
                'process_number' => $process->process_number,
                'demandante' => !empty($demandante) ? $demandante : '---',
                'demandado' => !empty($demandado) ? $demandado : '---',
                'action_date' => $action->action_date->format('d/m/Y'),
                'action_text' => $action->action,
                'annotation' => $action->annotation ?: '---',
                'start_date' => $action->start_date ? $action->start_date->format('d/m/Y') : null,
                'end_date' => $action->end_date ? $action->end_date->format('d/m/Y') : null,
                'registration_date' => $action->registration_date->format('d/m/Y'),
                'is_alert' => $isAlert,
                'matched_keywords' => $matchedKeywords,
            ];
        })->filter()->values();
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

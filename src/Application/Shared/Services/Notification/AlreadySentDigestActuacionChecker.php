<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Helpers\ProcessActionIdentityHelper;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Services\FijacionEstadoActionSplitter;
use Src\Domain\Process\Services\GroupProcessActionsService;

/**
 * Detects actuaciones that already went out in a sent consolidado for an organization.
 *
 * Covers exact content fingerprints and fijación/auto (or edicto/sentencia) pairs that
 * later reappear as a bare "Fijación Estado" after import/split.
 */
readonly class AlreadySentDigestActuacionChecker
{
    public function __construct(
        private FijacionEstadoActionSplitter $fijacionEstadoActionSplitter,
        private GroupProcessActionsService $groupProcessActionsService,
    ) {}

    public function wasAlreadyIncludedInSentDigest(string $organizationId, ProcessAction $action): bool
    {
        $action->loadMissing('process');
        $processNumber = (string) ($action->process->process_number ?? '');
        if ($processNumber === '') {
            return false;
        }

        $registrationDate = $action->registration_date->format('Y-m-d');
        $pendingFingerprint = ProcessActionIdentityHelper::fingerprint($action);
        $sentActions = $this->loadDigestSentActionsForRadicadoDate(
            $organizationId,
            $processNumber,
            $registrationDate,
        );

        foreach ($sentActions as $sentAction) {
            if ((string) $sentAction->id === (string) $action->id) {
                return true;
            }

            if (ProcessActionIdentityHelper::fingerprint($sentAction) === $pendingFingerprint) {
                return true;
            }

            if ($this->areLogicalDuplicatesOrPair($action, $sentAction)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, OrganizationNotification>  $notifications
     * @return array{0: Collection<int, OrganizationNotification>, 1: Collection<int, OrganizationNotification>}
     */
    public function partitionPendingNotifications(string $organizationId, Collection $notifications): array
    {
        $keep = collect();
        $suppress = collect();

        foreach ($notifications as $notification) {
            $action = $notification->notifiable;
            if (! $action instanceof ProcessAction) {
                $keep->push($notification);

                continue;
            }

            if ($this->wasAlreadyIncludedInSentDigest($organizationId, $action)) {
                $suppress->push($notification);

                continue;
            }

            $keep->push($notification);
        }

        return [$keep->values(), $suppress->values()];
    }

    /**
     * Marks duplicate pending rows as email-notified so they never re-enter a consolidado.
     *
     * @param  Collection<int, OrganizationNotification>  $notifications
     */
    public function markSuppressedAsNotified(Collection $notifications): void
    {
        if ($notifications->isEmpty()) {
            return;
        }

        OrganizationNotification::query()
            ->whereIn('id', $notifications->pluck('id'))
            ->update([
                'is_email_notified' => true,
                'email_notified_at' => now(),
                'is_notified' => true,
                'notified_at' => now(),
            ]);

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('AlreadySentDigestActuacionChecker: suppressed already-sent actuaciones', [
                'count' => $notifications->count(),
                'notification_ids' => $notifications->pluck('id')->all(),
            ]);
    }

    /**
     * @return Collection<int, ProcessAction>
     */
    private function loadDigestSentActionsForRadicadoDate(
        string $organizationId,
        string $processNumber,
        string $registrationDate,
    ): Collection {
        $morphClass = (new ProcessAction)->getMorphClass();

        $actionIds = DB::table('organization_notifications')
            ->join('process_actions', 'organization_notifications.notifiable_id', '=', 'process_actions.id')
            ->join('processes', 'process_actions.process_id', '=', 'processes.id')
            ->where('organization_notifications.organization_id', $organizationId)
            ->where('organization_notifications.notifiable_type', $morphClass)
            ->where('organization_notifications.is_email_notified', true)
            ->whereNotNull('organization_notifications.notification_digest_id')
            ->where('processes.process_number', $processNumber)
            ->whereDate('process_actions.registration_date', $registrationDate)
            ->pluck('process_actions.id');

        if ($actionIds->isEmpty()) {
            return collect();
        }

        return ProcessAction::query()
            ->whereIn('id', $actionIds->all())
            ->get();
    }

    private function areLogicalDuplicatesOrPair(ProcessAction $pending, ProcessAction $sent): bool
    {
        $pendingText = (string) $pending->action;
        $sentText = (string) $sent->action;

        $pendingEstadoOnly = $this->fijacionEstadoActionSplitter->isEstadoPairLabel($pendingText);
        $sentEstadoOnly = $this->fijacionEstadoActionSplitter->isEstadoPairLabel($sentText);

        if ($pendingEstadoOnly && $sentEstadoOnly) {
            return true;
        }

        // Combined "Fijación Estado Auto …" already sent → bare fijación must not resend.
        if ($pendingEstadoOnly && $this->isCombinedEstadoDecision($sentText)) {
            return true;
        }

        if ($sentEstadoOnly && $this->isCombinedEstadoDecision($pendingText)) {
            return true;
        }

        $pendingRow = $this->toPairingRow($pending);
        $sentRow = $this->toPairingRow($sent);
        $tagged = $this->groupProcessActionsService->handle(collect([$pendingRow, $sentRow]));

        $pendingTagged = $tagged->firstWhere('process_action_id', $pending->id);
        $sentTagged = $tagged->firstWhere('process_action_id', $sent->id);

        if (! is_array($pendingTagged) || ! is_array($sentTagged)) {
            return false;
        }

        return isset($pendingTagged['notified_action_id'], $sentTagged['fijacion_action_id'])
            || isset($pendingTagged['fijacion_action_id'], $sentTagged['notified_action_id']);
    }

    private function isCombinedEstadoDecision(string $actionText): bool
    {
        $parts = $this->fijacionEstadoActionSplitter->split($actionText);

        return count($parts) === 2;
    }

    /**
     * @return array<string, mixed>
     */
    private function toPairingRow(ProcessAction $action): array
    {
        $action->loadMissing('process');

        return [
            'process_action_id' => $action->id,
            'process_number' => (string) ($action->process->process_number ?? ''),
            'action_text' => (string) $action->action,
            'action' => (string) $action->action,
            'annotation' => (string) ($action->annotation ?? ''),
            'registration_date' => $action->registration_date->format('Y-m-d'),
            'cons_action' => $action->cons_action,
        ];
    }
}

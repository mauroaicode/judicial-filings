<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Src\Application\Admin\Process\Data\MarkProcessActionRemovedFromJudicialBranchData;
use Src\Application\Shared\Services\Notification\NotificationDispatcherService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

readonly class MarkProcessActionRemovedFromJudicialBranchService
{
    public const NOTIFICATION_TYPE = 'actuacion_eliminada_rama';

    public function __construct(
        private NotificationDispatcherService $notificationDispatcherService,
        private ResolveProcessActionNotifiedConsolidadosService $resolveProcessActionNotifiedConsolidadosService,
    ) {}

    /**
     * @return array{
     *     action: ProcessAction,
     *     notified_consolidados: list<array<string, mixed>>,
     *     pending_notifications_silenced: int,
     *     organizations_notified: int
     * }
     */
    public function handle(
        string $processId,
        string $actionId,
        MarkProcessActionRemovedFromJudicialBranchData $data,
        string $adminUserId,
    ): array {
        $process = Process::query()->find($processId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $action = ProcessAction::query()
            ->where('id', $actionId)
            ->where('process_id', $processId)
            ->first();

        if (! $action instanceof ProcessAction) {
            abort(404, __('process.action_not_found'));
        }

        if ($action->removed_from_judicial_branch_at !== null) {
            throw ValidationException::withMessages([
                'action' => [__('process.action_already_removed_from_judicial_branch')],
            ]);
        }

        $matchingActionIds = $this->matchingActionIds($action);
        $notifiedConsolidados = [];
        $silenced = 0;
        $organizationsNotified = 0;

        $action->loadMissing('process');

        DB::transaction(function () use (
            $action,
            $data,
            $adminUserId,
            $matchingActionIds,
            &$silenced,
            &$organizationsNotified,
        ): void {
            $now = now();

            ProcessAction::query()
                ->whereIn('id', $matchingActionIds->all())
                ->update([
                    'removed_from_judicial_branch_at' => $now,
                    'removed_from_judicial_branch_by' => $adminUserId,
                    'removed_from_judicial_branch_reason' => $data->reason,
                ]);

            $silenced = $this->silencePendingNotifications($matchingActionIds);

            if ($data->notify_organizations) {
                $organizationsNotified = $this->notifyInterestedOrganizations($action, $action->process);
            }
        });

        $action->refresh();

        $notifiedConsolidados = $this->resolveProcessActionNotifiedConsolidadosService
            ->forActions(collect([$action]))[$action->id] ?? [];

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('MarkProcessActionRemovedFromJudicialBranchService: actuación marcada como retirada en Rama', [
                'process_id' => $processId,
                'action_id' => $actionId,
                'admin_user_id' => $adminUserId,
                'notify_organizations' => $data->notify_organizations,
                'pending_notifications_silenced' => $silenced,
                'organizations_notified' => $organizationsNotified,
            ]);

        return [
            'action' => $action,
            'notified_consolidados' => $notifiedConsolidados,
            'pending_notifications_silenced' => $silenced,
            'organizations_notified' => $organizationsNotified,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function matchingActionIds(ProcessAction $action): \Illuminate\Support\Collection
    {
        $action->loadMissing('process');
        $processNumber = (string) ($action->process->process_number ?? '');

        if ($processNumber === '') {
            return collect([$action->id]);
        }

        return ProcessAction::query()
            ->whereMatchesContentIdentity($processNumber, $action)
            ->pluck('id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $matchingActionIds
     */
    private function silencePendingNotifications(\Illuminate\Support\Collection $matchingActionIds): int
    {
        $morphClass = (new ProcessAction)->getMorphClass();
        $silenced = 0;

        OrganizationNotification::query()
            ->where('notifiable_type', $morphClass)
            ->whereIn('notifiable_id', $matchingActionIds->all())
            ->where('is_email_notified', false)
            ->whereNull('notification_digest_id')
            ->orderBy('organization_id')
            ->chunkById(500, function ($notifications) use (&$silenced): void {
                $ids = $notifications->pluck('id')->all();
                if ($ids === []) {
                    return;
                }

                $updated = OrganizationNotification::query()
                    ->whereIn('id', $ids)
                    ->update([
                        'is_email_notified' => true,
                        'email_notified_at' => now(),
                        'is_notified' => true,
                        'notified_at' => now(),
                        'notification_digest_id' => null,
                    ]);

                $silenced += $updated;
            });

        return $silenced;
    }

    private function notifyInterestedOrganizations(ProcessAction $action, Process $process): int
    {
        $organizations = $process->organizations()
            ->where('organizations.is_active', true)
            ->where('organizations.is_process_tracking_enabled', true)
            ->wherePivot('is_active', true)
            ->get();

        $morphClass = $action->getMorphClass();
        $notified = 0;

        foreach ($organizations as $organization) {
            if (! $organization instanceof Organization || ! $organization->canTrackProcesses()) {
                continue;
            }

            $notification = OrganizationNotification::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'notifiable_id' => $action->id,
                    'notifiable_type' => $morphClass,
                    'notification_type' => self::NOTIFICATION_TYPE,
                    'severity_color' => null,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'is_viewed' => false,
                    'is_notified' => false,
                    'is_email_notified' => false,
                ],
            );

            if ($notification->wasRecentlyCreated || ! $notification->is_notified) {
                $this->notificationDispatcherService->handle($notification);
                $notified++;
            }
        }

        return $notified;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Helpers\ProcessActionIdentityHelper;
use Src\Application\Shared\Helpers\ProcessPhantomInstanceHelper;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

final class RepairPhantomProcessInstancesService
{
    /**
     * @return list<string>
     */
    public function findAffectedRadicados(): array
    {
        $fromPhantoms = Process::query()
            ->select('process_number')
            ->groupBy('process_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('process_number')
            ->map(fn ($value): string => (string) $value)
            ->filter(function (string $processNumber): bool {
                $siblings = Process::query()->where('process_number', $processNumber)->get();

                return $siblings->contains(
                    fn (Process $process): bool => ProcessPhantomInstanceHelper::isLikelyPhantomForOrganizationTracking($process, $siblings),
                );
            })
            ->values();

        $multiInstanceNumbers = Process::query()
            ->select('process_number')
            ->groupBy('process_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('process_number')
            ->map(fn ($value): string => (string) $value);

        $fromDuplicateContent = $multiInstanceNumbers
            ->filter(function (string $processNumber): bool {
                $actions = ProcessAction::query()
                    ->whereHas('process', fn (\Illuminate\Contracts\Database\Query\Builder $query) => $query->where('process_number', $processNumber))
                    ->get();

                return $this->hasDuplicateFingerprints($actions);
            });

        return $fromPhantoms
            ->merge($fromDuplicateContent)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function repairRadicado(string $processNumber, bool $dryRun = false): RepairPhantomProcessInstancesResult
    {
        $processes = Process::query()
            ->where('process_number', $processNumber)->oldest()
            ->get();

        if ($processes->isEmpty()) {
            return new RepairPhantomProcessInstancesResult(0, 0, 0);
        }

        $phantomProcesses = $processes
            ->filter(fn (Process $process): bool => ProcessPhantomInstanceHelper::isLikelyPhantomForOrganizationTracking($process, $processes))
            ->values();

        $phantomCount = $phantomProcesses->count();

        $actions = ProcessAction::query()
            ->whereIn('process_id', $processes->pluck('id'))->oldest()
            ->get();

        $duplicateGroups = $actions
            ->groupBy(fn (ProcessAction $action): string => ProcessActionIdentityHelper::fingerprint($action))
            ->filter(fn (Collection $group): bool => $group->count() > 1);

        $actionsRemoved = 0;
        $notificationsRemoved = 0;
        $organizationLinksDeactivated = 0;
        $morphClass = (new ProcessAction)->getMorphClass();
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        if ($dryRun) {
            foreach ($duplicateGroups as $group) {
                $canonical = ProcessActionIdentityHelper::pickCanonical($group, $processes);
                $actionsRemoved += $group->filter(fn (ProcessAction $action): bool => $action->id !== $canonical->id)->count();
            }

            $organizationLinksDeactivated = $phantomProcesses->isEmpty()
                ? 0
                : OrganizationProcess::query()
                    ->whereIn('process_id', $phantomProcesses->pluck('id'))
                    ->where(function (\Illuminate\Contracts\Database\Query\Builder $query): void {
                        $query->where('is_active', true)
                            ->orWhere('status', '!=', OrganizationProcessStatus::INACTIVE->value);
                    })
                    ->count();

            return new RepairPhantomProcessInstancesResult(
                $actionsRemoved,
                0,
                $phantomCount,
                $organizationLinksDeactivated,
            );
        }

        DB::transaction(function () use (
            $duplicateGroups,
            $processes,
            $phantomProcesses,
            $processNumber,
            $morphClass,
            $logChannel,
            &$actionsRemoved,
            &$notificationsRemoved,
            &$organizationLinksDeactivated,
        ): void {
            foreach ($duplicateGroups as $fingerprint => $group) {
                $canonical = ProcessActionIdentityHelper::pickCanonical($group, $processes);
                $duplicates = $group->filter(fn (ProcessAction $action): bool => $action->id !== $canonical->id);

                foreach ($duplicates as $duplicate) {
                    Log::channel($logChannel)->info('RepairPhantomProcessInstancesService: removing duplicate actuacion', [
                        'process_number' => $processes->first()->process_number,
                        'fingerprint' => $fingerprint,
                        'canonical_action_id' => $canonical->id,
                        'duplicate_action_id' => $duplicate->id,
                        'duplicate_process_id' => $duplicate->process_id,
                        'action_registration_id' => $duplicate->action_registration_id,
                    ]);

                    $notificationsRemoved += OrganizationNotification::query()
                        ->where('notifiable_type', $morphClass)
                        ->where('notifiable_id', $duplicate->id)
                        ->delete();

                    $this->deleteActionGraph($duplicate);
                    $actionsRemoved++;
                }

                $notificationsRemoved += $this->removeDuplicateNotificationsForCanonical(
                    $canonical,
                    $morphClass,
                    $processNumber,
                );
            }

            foreach ($processes as $process) {
                $dbMaxDate = $process->actions()->max('action_date');
                $process->update([
                    'last_activity_date' => $dbMaxDate,
                ]);
            }

            foreach ($phantomProcesses as $phantomProcess) {
                $updated = OrganizationProcess::query()
                    ->where('process_id', $phantomProcess->id)
                    ->where(function (\Illuminate\Contracts\Database\Query\Builder $query): void {
                        $query->where('is_active', true)
                            ->orWhere('status', '!=', OrganizationProcessStatus::INACTIVE->value);
                    })
                    ->update([
                        'is_active' => false,
                        'status' => OrganizationProcessStatus::INACTIVE->value,
                        'updated_at' => now(),
                    ]);

                if ($updated > 0) {
                    $organizationLinksDeactivated += $updated;

                    Log::channel($logChannel)->info('RepairPhantomProcessInstancesService: deactivated org links on phantom instance', [
                        'process_number' => $processNumber,
                        'process_id' => $phantomProcess->id,
                        'links_deactivated' => $updated,
                    ]);
                }
            }
        });

        return new RepairPhantomProcessInstancesResult(
            $actionsRemoved,
            $notificationsRemoved,
            $phantomCount,
            $organizationLinksDeactivated,
        );
    }

    /**
     * @param  Collection<int, ProcessAction>  $actions
     */
    private function hasDuplicateFingerprints(Collection $actions): bool
    {
        return $actions
            ->groupBy(fn (ProcessAction $action): string => ProcessActionIdentityHelper::fingerprint($action))
            ->contains(fn (Collection $group): bool => $group->count() > 1);
    }

    private function deleteActionGraph(ProcessAction $action): void
    {
        DB::table('process_action_alert_action_keyword')
            ->where('process_action_id', $action->id)
            ->delete();

        DB::table('process_action_alert_highlights')
            ->where('process_action_id', $action->id)
            ->delete();

        $action->delete();
    }

    private function removeDuplicateNotificationsForCanonical(
        ProcessAction $canonical,
        string $morphClass,
        string $processNumber,
    ): int {
        $matchingActionIds = ProcessAction::query()
            ->whereMatchesContentIdentity($processNumber, $canonical)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if ($matchingActionIds === []) {
            return 0;
        }

        $notifications = OrganizationNotification::query()
            ->where('notifiable_type', $morphClass)
            ->whereIn('notifiable_id', $matchingActionIds)
            ->get();

        $removed = 0;
        $canonicalId = (string) $canonical->id;

        $notifications
            ->groupBy(fn (OrganizationNotification $notification): string => implode('|', [
                $notification->organization_id,
                $notification->notification_type,
                (string) $notification->severity_color,
            ]))
            ->each(function (Collection $group) use ($canonicalId, &$removed): void {
                if ($group->count() <= 1) {
                    return;
                }

                $keeper = $group->firstWhere('notifiable_id', $canonicalId) ?? $group->first();

                foreach ($group as $notification) {
                    if ($notification->id === $keeper->id) {
                        if ($notification->notifiable_id !== $canonicalId) {
                            $notification->update(['notifiable_id' => $canonicalId]);
                        }

                        continue;
                    }

                    $notification->delete();
                    $removed++;
                }
            });

        return $removed;
    }
}

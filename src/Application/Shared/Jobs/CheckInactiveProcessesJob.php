<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Process\Timeline\Services\RecordSemaphoreTimelineEventService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;

class CheckInactiveProcessesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(RecordSemaphoreTimelineEventService $recordSemaphoreTimelineEventService): void
    {
        $yellowThreshold = ProcessAlertLevelHelper::yellowThresholdDays();
        $redThreshold = ProcessAlertLevelHelper::redThresholdDays();

        // Demandante: inactividad prolongada es mala.
        $this->evaluateByLastActivityDate(
            ProcessLawyerRole::PLAINTIFF->value,
            $redThreshold,
            null,
            'red',
            'inactividad_roja',
            $recordSemaphoreTimelineEventService,
        );
        $this->evaluateByLastActivityDate(
            ProcessLawyerRole::PLAINTIFF->value,
            $yellowThreshold,
            $redThreshold,
            'yellow',
            'inactividad_amarilla',
            $recordSemaphoreTimelineEventService,
        );

        // Demandado: actividad reciente es mala; inactividad prolongada es favorable.
        $this->evaluateByRecentActivity(
            ProcessLawyerRole::DEFENDANT->value,
            $yellowThreshold,
            'red',
            'actividad_roja',
            $recordSemaphoreTimelineEventService,
        );
        $this->evaluateByLastActivityDate(
            ProcessLawyerRole::DEFENDANT->value,
            $yellowThreshold,
            $redThreshold,
            'yellow',
            'actividad_amarilla',
            $recordSemaphoreTimelineEventService,
        );
        $this->evaluateByLastActivityDate(
            ProcessLawyerRole::DEFENDANT->value,
            $redThreshold,
            null,
            'green',
            'inactividad_verde',
            $recordSemaphoreTimelineEventService,
        );
    }

    /**
     * Processes whose last activity falls in [minDays, maxDays) days ago.
     */
    private function evaluateByLastActivityDate(
        string $role,
        int $minDays,
        ?int $maxDays,
        string $targetAlertLevel,
        string $notificationType,
        RecordSemaphoreTimelineEventService $recordSemaphoreTimelineEventService,
    ): void {
        $query = Process::query()
            ->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($role, $targetAlertLevel): void {
                $q->where('organizations.is_active', true)
                    ->where('organization_processes.lawyer_role', $role)
                    ->where('organization_processes.is_active', true)
                    ->where('organization_processes.status', '!=', \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::SUSPENDED->value)
                    ->where(function (\Illuminate\Contracts\Database\Query\Builder $subQ) use ($targetAlertLevel): void {
                        $subQ->where('organization_processes.inactivity_alert_level', '!=', $targetAlertLevel)
                            ->orWhereNull('organization_processes.inactivity_alert_level');
                    });
            })
            ->where('last_activity_date', '<=', \Illuminate\Support\Facades\Date::now()->subDays($minDays));

        if ($maxDays !== null) {
            $query->where('last_activity_date', '>', \Illuminate\Support\Facades\Date::now()->subDays($maxDays));
        }

        $this->persistAlertsForProcesses(
            $query->get(),
            $role,
            $targetAlertLevel,
            $notificationType,
            $recordSemaphoreTimelineEventService,
        );
    }

    /**
     * Processes with activity more recent than the threshold (demandado en riesgo).
     */
    private function evaluateByRecentActivity(
        string $role,
        int $withinDays,
        string $targetAlertLevel,
        string $notificationType,
        RecordSemaphoreTimelineEventService $recordSemaphoreTimelineEventService,
    ): void {
        $query = Process::query()
            ->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($role, $targetAlertLevel): void {
                $q->where('organizations.is_active', true)
                    ->where('organization_processes.lawyer_role', $role)
                    ->where('organization_processes.is_active', true)
                    ->where('organization_processes.status', '!=', \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::SUSPENDED->value)
                    ->where(function (\Illuminate\Contracts\Database\Query\Builder $subQ) use ($targetAlertLevel): void {
                        $subQ->where('organization_processes.inactivity_alert_level', '!=', $targetAlertLevel)
                            ->orWhereNull('organization_processes.inactivity_alert_level');
                    });
            })
            ->where('last_activity_date', '>', \Illuminate\Support\Facades\Date::now()->subDays($withinDays));

        $this->persistAlertsForProcesses(
            $query->get(),
            $role,
            $targetAlertLevel,
            $notificationType,
            $recordSemaphoreTimelineEventService,
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Process>  $processes
     */
    private function persistAlertsForProcesses(
        \Illuminate\Support\Collection $processes,
        string $role,
        string $targetAlertLevel,
        string $notificationType,
        RecordSemaphoreTimelineEventService $recordSemaphoreTimelineEventService,
    ): void {
        foreach ($processes as $process) {
            DB::transaction(function () use ($process, $role, $targetAlertLevel, $notificationType, $recordSemaphoreTimelineEventService): void {
                $organizationsToAlert = $process->organizations()
                    ->wherePivot('is_active', true)
                    ->wherePivot('status', '!=', \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::SUSPENDED->value)
                    ->wherePivot('lawyer_role', $role)
                    ->where(function (\Illuminate\Contracts\Database\Query\Builder $q) use ($targetAlertLevel): void {
                        $q->where('organization_processes.inactivity_alert_level', '!=', $targetAlertLevel)
                            ->orWhereNull('organization_processes.inactivity_alert_level');
                    })
                    ->get();

                if ($organizationsToAlert->isEmpty()) {
                    return;
                }

                foreach ($organizationsToAlert as $organization) {
                    $previousAlertLevel = $organization->pivot->inactivity_alert_level;

                    if (
                        $targetAlertLevel === 'yellow'
                        && $role === ProcessLawyerRole::PLAINTIFF->value
                        && $organization->pivot->inactivity_alert_level === 'red'
                    ) {
                        continue;
                    }

                    $process->organizations()->updateExistingPivot($organization->id, [
                        'inactivity_alert_level' => $targetAlertLevel,
                    ]);

                    $recordSemaphoreTimelineEventService->handle(
                        process: $process,
                        organizationId: $organization->id,
                        from: $previousAlertLevel,
                        to: $targetAlertLevel,
                        reason: $notificationType,
                        lawyerRole: $role,
                    );

                    OrganizationNotification::query()->firstOrCreate(
                        [
                            'organization_id' => $organization->id,
                            'notifiable_id' => $process->id,
                            'notifiable_type' => $process->getMorphClass(),
                            'notification_type' => $notificationType,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'is_viewed' => false,
                            'is_notified' => false,
                        ]
                    );
                }
            });
        }
    }
}

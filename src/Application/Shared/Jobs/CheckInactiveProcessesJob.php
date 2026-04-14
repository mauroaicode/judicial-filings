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
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;

class CheckInactiveProcessesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Plaintiff >= 90 days => Red
        $this->evaluateInactivity(ProcessLawyerRole::PLAINTIFF->value, 90, null, 'red', 'inactividad_roja');

        // Plaintiff 45-89 days => Yellow
        $this->evaluateInactivity(ProcessLawyerRole::PLAINTIFF->value, 45, 90, 'yellow', 'inactividad_amarilla');

        // Defendant >= 90 days => Green
        $this->evaluateInactivity(ProcessLawyerRole::DEFENDANT->value, 90, null, 'green', 'inactividad_verde');
    }

    /**
     * Evaluate process inactivity based on a set of rules.
     */
    private function evaluateInactivity(string $role, int $minDays, ?int $maxDays, string $targetAlertLevel, string $notificationType): void
    {
        $query = Process::query()
            ->whereHas('organizations', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($role, $targetAlertLevel): void {
                $q->where('organization_processes.lawyer_role', $role)
                    ->where('organization_processes.is_active', true)
                    ->where(function (\Illuminate\Contracts\Database\Query\Builder $subQ) use ($targetAlertLevel): void {
                        $subQ->where('organization_processes.inactivity_alert_level', '!=', $targetAlertLevel)
                            ->orWhereNull('organization_processes.inactivity_alert_level');
                    });
            })
            ->where('last_activity_date', '<=', \Illuminate\Support\Facades\Date::now()->subDays($minDays));

        if ($maxDays !== null) {
            $query->where('last_activity_date', '>', \Illuminate\Support\Facades\Date::now()->subDays($maxDays));
        }

        $processes = $query->get();

        foreach ($processes as $process) {
            DB::transaction(function () use ($process, $role, $targetAlertLevel, $notificationType): void {
                $organizationsToAlert = $process->organizations()
                    ->wherePivot('is_active', true)
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
                    // If target is yellow, do not downgrade from red
                    if ($targetAlertLevel === 'yellow' && $organization->pivot->inactivity_alert_level === 'red') {
                        continue;
                    }

                    // Update pivot
                    $process->organizations()->updateExistingPivot($organization->id, [
                        'inactivity_alert_level' => $targetAlertLevel,
                    ]);

                    // Create the notification record
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

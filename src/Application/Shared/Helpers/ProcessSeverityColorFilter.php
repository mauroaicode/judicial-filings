<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Illuminate\Contracts\Database\Query\Builder;
use Src\Domain\Shared\Enums\SeverityColor;

/**
 * Applies severity_color filters on organization_processes pivot queries,
 * aligned with ProcessAlertLevelHelper (display) semantics.
 */
final class ProcessSeverityColorFilter
{
    public static function apply(Builder $query, string $severityColor): void
    {
        match ($severityColor) {
            'none' => self::applyNone($query),
            SeverityColor::GREEN->value => self::applyGreen($query),
            default => $query->where('organization_processes.inactivity_alert_level', $severityColor),
        };
    }

    private static function applyNone(Builder $query): void
    {
        $cutoff = self::movingCutoffDate();

        $query->where(function (Builder $q): void {
            $q->whereNull('organization_processes.inactivity_alert_level')
                ->orWhereExists(function (Builder $sub): void {
                    $sub->selectRaw('1')
                        ->from('processes')
                        ->whereColumn('processes.id', 'organization_processes.process_id')
                        ->whereNull('processes.last_activity_date');
                });
        });

        // Exclude plaintiff processes that would display as green (recent activity).
        $query->where(function (Builder $q) use ($cutoff): void {
            $q->whereNull('organization_processes.lawyer_role')
                ->orWhere('organization_processes.lawyer_role', '!=', 'plaintiff')
                ->orWhereExists(function (Builder $sub) use ($cutoff): void {
                    $sub->selectRaw('1')
                        ->from('processes')
                        ->whereColumn('processes.id', 'organization_processes.process_id')
                        ->where(function (Builder $p) use ($cutoff): void {
                            $p->whereNull('processes.last_activity_date')
                                ->orWhere('processes.last_activity_date', '<=', $cutoff);
                        });
                });
        });
    }

    private static function applyGreen(Builder $query): void
    {
        $cutoff = self::movingCutoffDate();

        $query->where(function (Builder $q) use ($cutoff): void {
            // Stored green (e.g. defendant inactivity >= 90 days).
            $q->where('organization_processes.inactivity_alert_level', SeverityColor::GREEN->value)
                // Simulated green: plaintiff, no stored level, recent activity.
                ->orWhere(function (Builder $moving) use ($cutoff): void {
                    $moving->whereNull('organization_processes.inactivity_alert_level')
                        ->where('organization_processes.lawyer_role', 'plaintiff')
                        ->whereExists(function (Builder $sub) use ($cutoff): void {
                            $sub->selectRaw('1')
                                ->from('processes')
                                ->whereColumn('processes.id', 'organization_processes.process_id')
                                ->where('processes.last_activity_date', '>', $cutoff);
                        });
                });
        });
    }

    private static function movingCutoffDate(): string
    {
        $movingDays = (int) config('semaphores.moving_days_green', 30);

        return now()->subDays($movingDays)->startOfDay()->toDateString();
    }
}

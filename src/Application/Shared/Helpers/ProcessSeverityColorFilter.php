<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Illuminate\Contracts\Database\Query\Builder;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Shared\Enums\SeverityColor;

/**
 * Applies severity_color filters on organization_processes pivot queries,
 * aligned with ProcessAlertLevelHelper (display) semantics.
 */
final class ProcessSeverityColorFilter
{
    public static function apply(Builder $query, string $severityColor): void
    {
        $query->where('organization_processes.status', OrganizationProcessStatus::ACTIVE->value);

        match ($severityColor) {
            'none' => self::applyNone($query),
            SeverityColor::GREEN->value => self::applyGreen($query),
            SeverityColor::RED->value => self::applyRed($query),
            SeverityColor::YELLOW->value => self::applyYellow($query),
            default => $query->where('organization_processes.inactivity_alert_level', $severityColor),
        };
    }

    private static function applyNone(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('organization_processes.lawyer_role')
                ->orWhereExists(function (Builder $sub): void {
                    $sub->selectRaw('1')
                        ->from('processes')
                        ->whereColumn('processes.id', 'organization_processes.process_id')
                        ->whereNull('processes.last_activity_date');
                });
        });
    }

    private static function applyRed(Builder $query): void
    {
        $yellowCutoff = self::yellowCutoffDate();
        $redCutoff = self::redCutoffDate();

        $query->where(function (Builder $q) use ($yellowCutoff, $redCutoff): void {
            $q->where('organization_processes.inactivity_alert_level', SeverityColor::RED->value)
                ->orWhere(function (Builder $plaintiff) use ($redCutoff): void {
                    $plaintiff->where('organization_processes.lawyer_role', 'plaintiff')
                        ->whereExists(fn (Builder $sub): Builder => self::whereLastActivity($sub, '<=', $redCutoff));
                })
                ->orWhere(function (Builder $defendant) use ($yellowCutoff): void {
                    $defendant->where('organization_processes.lawyer_role', 'defendant')
                        ->whereExists(fn (Builder $sub): Builder => self::whereLastActivity($sub, '>', $yellowCutoff));
                });
        });
    }

    private static function applyYellow(Builder $query): void
    {
        $yellowCutoff = self::yellowCutoffDate();
        $redCutoff = self::redCutoffDate();

        $query->where(function (Builder $q) use ($yellowCutoff, $redCutoff): void {
            $q->where('organization_processes.inactivity_alert_level', SeverityColor::YELLOW->value)
                ->orWhereExists(function (Builder $sub) use ($yellowCutoff, $redCutoff): void {
                    $sub->selectRaw('1')
                        ->from('processes')
                        ->whereColumn('processes.id', 'organization_processes.process_id')
                        ->whereNotNull('organization_processes.lawyer_role')
                        ->where('processes.last_activity_date', '<=', $yellowCutoff)
                        ->where('processes.last_activity_date', '>', $redCutoff);
                });
        });
    }

    private static function applyGreen(Builder $query): void
    {
        $yellowCutoff = self::yellowCutoffDate();
        $redCutoff = self::redCutoffDate();

        $query->where(function (Builder $q) use ($yellowCutoff, $redCutoff): void {
            $q->where('organization_processes.inactivity_alert_level', SeverityColor::GREEN->value)
                ->orWhere(function (Builder $plaintiff) use ($yellowCutoff): void {
                    $plaintiff->where('organization_processes.lawyer_role', 'plaintiff')
                        ->whereExists(fn (Builder $sub): Builder => self::whereLastActivity($sub, '>', $yellowCutoff));
                })
                ->orWhere(function (Builder $defendant) use ($redCutoff): void {
                    $defendant->where('organization_processes.lawyer_role', 'defendant')
                        ->whereExists(fn (Builder $sub): Builder => self::whereLastActivity($sub, '<=', $redCutoff));
                });
        });
    }

    private static function whereLastActivity(Builder $sub, string $operator, string $cutoff): Builder
    {
        return $sub->selectRaw('1')
            ->from('processes')
            ->whereColumn('processes.id', 'organization_processes.process_id')
            ->where('processes.last_activity_date', $operator, $cutoff);
    }

    private static function yellowCutoffDate(): string
    {
        return now()->subDays(ProcessAlertLevelHelper::yellowThresholdDays())->startOfDay()->toDateString();
    }

    private static function redCutoffDate(): string
    {
        return now()->subDays(ProcessAlertLevelHelper::redThresholdDays())->startOfDay()->toDateString();
    }
}

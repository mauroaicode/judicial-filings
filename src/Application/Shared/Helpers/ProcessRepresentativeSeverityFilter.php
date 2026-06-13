<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;

/**
 * Semaphore filters at radicado level using the representative instance
 * (latest last_activity_date), aligned with ProcessIndexResource and stats cards.
 */
final class ProcessRepresentativeSeverityFilter
{
    /**
     * @param  Builder<Process>  $baseQuery  Query with org/pivot filters applied, without severity_color.
     * @return list<string>
     */
    public static function matchingProcessNumbers(
        Builder $baseQuery,
        string $organizationId,
        string $severityColor,
    ): array {
        /** @var Collection<int, Process> $processes */
        $processes = (clone $baseQuery)
            ->select(['processes.id', 'processes.process_number', 'processes.last_activity_date'])
            ->with(['organizations' => function ($query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            }])
            ->get();

        $numbers = [];

        foreach ($processes->groupBy('process_number') as $instances) {
            $representative = self::representativeInstance($instances);

            if (! $representative instanceof Process) {
                continue;
            }

            $color = self::resolveColor($representative, $organizationId);
            $matches = $severityColor === 'none'
                ? $color === null
                : $color === $severityColor;

            if ($matches) {
                $numbers[] = $representative->process_number;
            }
        }

        return $numbers;
    }

    /**
     * @param  Builder<Process>  $query  Query with org/pivot filters applied, without severity_color.
     */
    public static function apply(Builder $query, string $organizationId, string $severityColor): void
    {
        $numbers = self::matchingProcessNumbers($query, $organizationId, $severityColor);

        $query->whereIn('process_number', $numbers !== [] ? $numbers : ['']);
    }

    public static function resolveColor(Process $process, string $organizationId): ?string
    {
        $organization = $process->organizations->firstWhere('id', $organizationId);

        if ($organization === null || $organization->pivot === null) {
            return null;
        }

        $role = $organization->pivot->lawyer_role;
        $lawyerRole = is_string($role) ? ProcessLawyerRole::tryFrom($role) : null;

        return ProcessAlertLevelHelper::resolve(
            $organization->pivot->inactivity_alert_level,
            $process->last_activity_date,
            $lawyerRole,
        );
    }

    /**
     * @param  Collection<int, Process>  $instances
     */
    private static function representativeInstance(Collection $instances): ?Process
    {
        return $instances
            ->sortByDesc(fn (Process $process): int => $process->last_activity_date?->getTimestamp() ?? 0)
            ->first();
    }
}

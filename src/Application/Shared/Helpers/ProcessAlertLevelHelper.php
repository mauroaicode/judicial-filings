<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Carbon\CarbonInterface;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Shared\Enums\SeverityColor;

final class ProcessAlertLevelHelper
{
    /**
     * Resolves the alert level shown in API responses from last activity and lawyer role.
     */
    public static function resolve(
        ?string $storedAlertLevel,
        ?CarbonInterface $lastActivityDate,
        ?ProcessLawyerRole $lawyerRole,
    ): ?string {
        if (! $lawyerRole instanceof ProcessLawyerRole) {
            return null;
        }

        if (! $lastActivityDate instanceof CarbonInterface) {
            return $storedAlertLevel;
        }

        return self::calculate($lastActivityDate, $lawyerRole);
    }

    /**
     * Calculate alert level from days since last official activity.
     *
     * Demandante: mucha inactividad es mala (rojo/amarillo); actividad reciente es favorable (verde).
     * Demandado: actividad reciente es mala (rojo); mucha inactividad es favorable (verde).
     */
    public static function calculate(CarbonInterface $lastActivityDate, ProcessLawyerRole $lawyerRole): string
    {
        $days = (int) $lastActivityDate->copy()->startOfDay()->diffInDays(today()->startOfDay());
        $yellowThreshold = self::yellowThresholdDays();
        $redThreshold = self::redThresholdDays();

        return match ($lawyerRole) {
            ProcessLawyerRole::PLAINTIFF => match (true) {
                $days >= $redThreshold => SeverityColor::RED->value,
                $days >= $yellowThreshold => SeverityColor::YELLOW->value,
                default => SeverityColor::GREEN->value,
            },
            ProcessLawyerRole::DEFENDANT => match (true) {
                $days >= $redThreshold => SeverityColor::GREEN->value,
                $days >= $yellowThreshold => SeverityColor::YELLOW->value,
                default => SeverityColor::RED->value,
            },
        };
    }

    public static function yellowThresholdDays(): int
    {
        return (int) config(
            'semaphores.inactivity_thresholds.'.ProcessLawyerRole::PLAINTIFF->value.'.'.SeverityColor::YELLOW->value,
            45
        );
    }

    public static function redThresholdDays(): int
    {
        return (int) config(
            'semaphores.inactivity_thresholds.'.ProcessLawyerRole::PLAINTIFF->value.'.'.SeverityColor::RED->value,
            90
        );
    }
}

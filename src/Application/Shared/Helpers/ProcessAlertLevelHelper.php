<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Carbon\CarbonInterface;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Shared\Enums\SeverityColor;

final class ProcessAlertLevelHelper
{
    /**
     * Resolves the alert level shown in API responses.
     * Uses the pivot inactivity_alert_level when set; otherwise applies "moving green"
     * for plaintiff processes with recent activity (<= moving_days_green).
     */
    public static function resolve(
        ?string $storedAlertLevel,
        ?CarbonInterface $lastActivityDate,
        ?ProcessLawyerRole $lawyerRole,
    ): ?string {
        if ($storedAlertLevel !== null) {
            return $storedAlertLevel;
        }

        if (
            $lastActivityDate &&
            $lawyerRole === ProcessLawyerRole::PLAINTIFF
        ) {
            $movingDays = (int) config('semaphores.moving_days_green', 30);
            $daysSinceLast = today()->diffInDays($lastActivityDate->startOfDay());
            if ($daysSinceLast <= $movingDays) {
                return SeverityColor::GREEN->value;
            }
        }

        return null;
    }
}

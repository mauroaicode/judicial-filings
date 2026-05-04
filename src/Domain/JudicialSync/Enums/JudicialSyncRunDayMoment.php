<?php

declare(strict_types=1);

namespace Src\Domain\JudicialSync\Enums;

use Carbon\CarbonInterface;

/**
 * Segment of the local day for a sync run, based on {@see config('app.timezone')}.
 *
 * Boundaries (local hour, 24h clock):
 *  - mañana: 05:00–11:59
 *  - tarde:  12:00–17:59
 *  - noche:  18:00–04:59
 */
enum JudicialSyncRunDayMoment: string
{
    case Morning = 'mañana';
    case Afternoon = 'tarde';
    case Night = 'noche';

    public static function fromStartedAt(CarbonInterface $startedAt): self
    {
        $local = $startedAt->copy()->timezone((string) config('app.timezone'));
        $hour = (int) $local->format('G');

        return match (true) {
            $hour >= 5 && $hour < 12 => self::Morning,
            $hour >= 12 && $hour < 18 => self::Afternoon,
            default => self::Night,
        };
    }
}

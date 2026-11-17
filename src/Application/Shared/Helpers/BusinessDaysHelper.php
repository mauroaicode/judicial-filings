<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Carbon\CarbonInterface;
use Src\Application\Shared\Services\Process\ColombiaHolidayCalendar;

/**
 * Colombian business days: Monday–Friday excluding public holidays.
 */
final class BusinessDaysHelper
{
    public static function isBusinessDay(CarbonInterface $day): bool
    {
        $date = \Illuminate\Support\Facades\Date::parse($day->toDateString())->startOfDay();

        if ($date->isWeekend()) {
            return false;
        }

        return ! resolve(ColombiaHolidayCalendar::class)->isHoliday($date);
    }

    /**
     * Business days from $from (exclusive) to $to (inclusive).
     * Same-day → 0. If $to is before $from, returns a negative count.
     *
     * Matches the previous calendar semantics of "days until due" as closely as
     * possible, but skips weekends and Colombian holidays.
     */
    public static function daysUntil(CarbonInterface $to, ?CarbonInterface $from = null): int
    {
        $start = ($from ?? today())->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        if ($end->equalTo($start)) {
            return 0;
        }

        $forward = $end->greaterThan($start);
        $cursor = $start->copy();
        $count = 0;

        while (! $cursor->equalTo($end)) {
            $cursor = $forward ? $cursor->addDay() : $cursor->subDay();

            if (self::isBusinessDay($cursor)) {
                $count++;
            }
        }

        return $forward ? $count : -$count;
    }
}

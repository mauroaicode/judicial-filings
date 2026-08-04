<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Rmunate\Calendar\Colombia;
use Throwable;

/**
 * Loads Colombian public-holiday dates (via rmunate/calendario-colombia) for a range.
 * Used by {@see StaleReplicationDetector} so weekend/holiday lag does not false-alert.
 */
final class ColombiaHolidayCalendar
{
    /**
     * @return array<string, true> Map of Y-m-d => true for holidays in [from, to] inclusive
     */
    public function holidayDatesBetween(Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfDay()->toDateString();
        $end = $to->copy()->endOfDay()->toDateString();

        try {
            $rows = Colombia::whereBetween('full_date', [$start, $end])->get();
        } catch (Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->warning('ColombiaHolidayCalendar: failed to load holidays', [
                    'from' => $start,
                    'to' => $end,
                    'message' => $e->getMessage(),
                ]);

            return [];
        }

        /** @var array<string, true> $dates */
        $dates = [];
        foreach ($rows as $row) {
            $fullDate = (string) ($row->full_date ?? '');
            if ($fullDate !== '') {
                $dates[$fullDate] = true;
            }
        }

        return $dates;
    }

    public function isHoliday(Carbon $day): bool
    {
        try {
            return Colombia::isHoliday($day->toDateString());
        } catch (Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->warning('ColombiaHolidayCalendar: isHoliday failed', [
                    'date' => $day->toDateString(),
                    'message' => $e->getMessage(),
                ]);

            return false;
        }
    }
}

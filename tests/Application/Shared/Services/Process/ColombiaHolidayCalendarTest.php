<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Src\Application\Shared\Services\Process\ColombiaHolidayCalendar;

it('loads colombian holidays in a date range including batalla de boyaca 2026', function (): void {
    $calendar = app(ColombiaHolidayCalendar::class);

    $dates = $calendar->holidayDatesBetween(
        Date::parse('2026-08-01'),
        Date::parse('2026-08-10'),
    );

    expect($dates)->toHaveKey('2026-08-07')
        ->and($calendar->isHoliday(Date::parse('2026-08-07')))->toBeTrue()
        ->and($calendar->isHoliday(Date::parse('2026-08-06')))->toBeFalse();
});

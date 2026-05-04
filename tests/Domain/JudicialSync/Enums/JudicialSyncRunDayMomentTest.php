<?php

declare(strict_types=1);

use Carbon\Carbon;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunDayMoment;

it('segments the local day into mañana, tarde or noche', function (string $localDateTime, string $expectedMoment): void {
    /** @var string $timezone */
    $timezone = config('app.timezone');
    $startedAt = Carbon::parse($localDateTime, $timezone);

    expect(JudicialSyncRunDayMoment::fromStartedAt($startedAt)->value)->toBe($expectedMoment);
})->with([
    ['2026-06-01 04:59:59', JudicialSyncRunDayMoment::Night->value],
    ['2026-06-01 05:00:00', JudicialSyncRunDayMoment::Morning->value],
    ['2026-06-01 11:59:59', JudicialSyncRunDayMoment::Morning->value],
    ['2026-06-01 12:00:00', JudicialSyncRunDayMoment::Afternoon->value],
    ['2026-06-01 17:59:59', JudicialSyncRunDayMoment::Afternoon->value],
    ['2026-06-01 18:00:00', JudicialSyncRunDayMoment::Night->value],
]);

<?php

declare(strict_types=1);

namespace Tests\Application\Shared\Helpers;

use Illuminate\Support\Carbon;
use Src\Application\Shared\Helpers\BusinessDaysHelper;

beforeEach(function (): void {
    Carbon::setTestNow('2026-03-13 08:00:00'); // Friday (no Colombian holiday that week)
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('does not count weekends as business days', function (): void {
    // Friday → Monday: only Monday counts
    expect(BusinessDaysHelper::daysUntil(Carbon::parse('2026-03-16')))->toBe(1);
});

it('counts weekdays between friday and wednesday as three business days', function (): void {
    // Fri → Wed: Mon, Tue, Wed
    expect(BusinessDaysHelper::daysUntil(Carbon::parse('2026-03-18')))->toBe(3);
});

it('returns zero when the target is today', function (): void {
    expect(BusinessDaysHelper::daysUntil(Carbon::parse('2026-03-13')))->toBe(0);
});

it('skips colombian holidays when counting business days', function (): void {
    Carbon::setTestNow('2026-08-06 08:00:00'); // Thursday before Batalla de Boyacá (Fri 2026-08-07)

    // Thu → Mon 10: Fri holiday skipped, only Mon counts
    expect(BusinessDaysHelper::isBusinessDay(Carbon::parse('2026-08-07')))->toBeFalse()
        ->and(BusinessDaysHelper::daysUntil(Carbon::parse('2026-08-10')))->toBe(1);
});

it('treats weekdays that are not holidays as business days', function (): void {
    expect(BusinessDaysHelper::isBusinessDay(Carbon::parse('2026-03-13')))->toBeTrue()
        ->and(BusinessDaysHelper::isBusinessDay(Carbon::parse('2026-03-14')))->toBeFalse()
        ->and(BusinessDaysHelper::isBusinessDay(Carbon::parse('2026-03-15')))->toBeFalse();
});

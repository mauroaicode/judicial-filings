<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Shared\Enums\SeverityColor;

it('returns green for plaintiff with activity within 45 days', function (): void {
    Carbon::setTestNow('2026-06-09 12:00:00');

    expect(ProcessAlertLevelHelper::calculate(
        Carbon::parse('2026-06-01'),
        ProcessLawyerRole::PLAINTIFF,
    ))->toBe(SeverityColor::GREEN->value);
});

it('returns yellow for plaintiff inactive between 45 and 89 days', function (): void {
    Carbon::setTestNow('2026-06-09 12:00:00');

    expect(ProcessAlertLevelHelper::calculate(
        Carbon::parse('2026-04-01'),
        ProcessLawyerRole::PLAINTIFF,
    ))->toBe(SeverityColor::YELLOW->value);
});

it('returns red for plaintiff inactive 90 days or more', function (): void {
    Carbon::setTestNow('2026-06-09 12:00:00');

    expect(ProcessAlertLevelHelper::calculate(
        Carbon::parse('2026-02-01'),
        ProcessLawyerRole::PLAINTIFF,
    ))->toBe(SeverityColor::RED->value);
});

it('returns red for defendant with activity within 45 days', function (): void {
    Carbon::setTestNow('2026-06-09 12:00:00');

    expect(ProcessAlertLevelHelper::calculate(
        Carbon::parse('2026-06-01'),
        ProcessLawyerRole::DEFENDANT,
    ))->toBe(SeverityColor::RED->value);
});

it('returns yellow for defendant inactive between 45 and 89 days', function (): void {
    Carbon::setTestNow('2026-06-09 12:00:00');

    expect(ProcessAlertLevelHelper::calculate(
        Carbon::parse('2026-04-01'),
        ProcessLawyerRole::DEFENDANT,
    ))->toBe(SeverityColor::YELLOW->value);
});

it('returns green for defendant inactive 90 days or more', function (): void {
    Carbon::setTestNow('2026-06-09 12:00:00');

    expect(ProcessAlertLevelHelper::calculate(
        Carbon::parse('2026-02-01'),
        ProcessLawyerRole::DEFENDANT,
    ))->toBe(SeverityColor::GREEN->value);
});

it('resolve returns null when lawyer role is missing', function (): void {
    Carbon::setTestNow('2026-06-09 12:00:00');

    expect(ProcessAlertLevelHelper::resolve('yellow', Carbon::parse('2026-06-01'), null))->toBeNull();
});

it('resolve falls back to stored level when last activity date is missing', function (): void {
    expect(ProcessAlertLevelHelper::resolve('yellow', null, ProcessLawyerRole::PLAINTIFF))->toBe('yellow');
});

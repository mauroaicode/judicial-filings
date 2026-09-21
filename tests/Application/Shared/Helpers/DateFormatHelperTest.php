<?php

declare(strict_types=1);

use Src\Application\Shared\Helpers\DateFormatHelper;

it('parses iso, slash and spanish long dates for digest filters', function (): void {
    app()->setLocale('es');

    expect(DateFormatHelper::tryParseDate('2026-09-18')?->toDateString())->toBe('2026-09-18')
        ->and(DateFormatHelper::tryParseDate('18/09/2026')?->toDateString())->toBe('2026-09-18')
        ->and(DateFormatHelper::tryParseDate('18 de septiembre de 2026')?->toDateString())->toBe('2026-09-18')
        ->and(DateFormatHelper::tryParseDate('18 De Septiembre De 2026')?->toDateString())->toBe('2026-09-18')
        ->and(DateFormatHelper::tryParseDate(null))->toBeNull()
        ->and(DateFormatHelper::tryParseDate(''))->toBeNull()
        ->and(DateFormatHelper::tryParseDate('-'))->toBeNull()
        ->and(DateFormatHelper::tryParseDate('---'))->toBeNull();
});

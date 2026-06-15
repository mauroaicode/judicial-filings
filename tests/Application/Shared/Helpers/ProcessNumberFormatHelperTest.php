<?php

declare(strict_types=1);

use Src\Application\Shared\Helpers\ProcessNumberFormatHelper;

it('formats a 23 digit process number with hyphens', function (): void {
    expect(ProcessNumberFormatHelper::format('76001333301820180024701'))
        ->toBe('76-001-33-33-018-2018-00247-01');
});

it('formats process numbers that already contain hyphens', function (): void {
    expect(ProcessNumberFormatHelper::format('76-001-33-33-018-2018-00247-01'))
        ->toBe('76-001-33-33-018-2018-00247-01');
});

it('returns null for empty values', function (): void {
    expect(ProcessNumberFormatHelper::format(null))->toBeNull();
    expect(ProcessNumberFormatHelper::format(''))->toBeNull();
});

it('returns the original value when the digit count is invalid', function (): void {
    expect(ProcessNumberFormatHelper::format('12345'))->toBe('12345');
});

it('returns a translated fallback for display when value is empty', function (): void {
    expect(ProcessNumberFormatHelper::display(null))->toBe(__('task.no_process_associated'));
});

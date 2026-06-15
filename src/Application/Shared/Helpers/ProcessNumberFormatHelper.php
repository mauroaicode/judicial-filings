<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

final class ProcessNumberFormatHelper
{
    /**
     * Colombian judicial filing number segment lengths (23 digits total).
     *
     * @var list<int>
     */
    private const SEGMENT_LENGTHS = [2, 3, 2, 2, 3, 4, 5, 2];

    /**
     * Formats a 23-digit process number as 76-001-33-33-018-2018-00247-01.
     * Returns the original value when it cannot be normalized.
     */
    public static function format(?string $processNumber): ?string
    {
        if ($processNumber === null || trim($processNumber) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $processNumber) ?? '';

        if (strlen($digits) !== 23) {
            return trim($processNumber);
        }

        $offset = 0;
        $segments = [];

        foreach (self::SEGMENT_LENGTHS as $length) {
            $segments[] = substr($digits, $offset, $length);
            $offset += $length;
        }

        return implode('-', $segments);
    }

    /**
     * Formats for display, falling back to the raw value or a translated placeholder.
     */
    public static function display(?string $processNumber, ?string $fallback = null): string
    {
        $formatted = self::format($processNumber);

        if ($formatted === null || $formatted === '') {
            return $fallback ?? __('task.no_process_associated');
        }

        return $formatted;
    }
}

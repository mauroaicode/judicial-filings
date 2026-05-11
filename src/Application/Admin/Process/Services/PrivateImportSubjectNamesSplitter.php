<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

final class PrivateImportSubjectNamesSplitter
{
    /**
     * @return list<string> trimmed non-empty fragments
     */
    public static function split(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        /** @var list<string> $parts */
        $parts = preg_split('/\s*,\s*/u', $raw) ?: [];

        $out = [];
        foreach ($parts as $part) {
            $segment = trim($part);
            if ($segment === '') {
                continue;
            }

            if (self::isNoisePlaceholder($segment)) {
                continue;
            }

            $out[] = $segment;
        }

        return $out;
    }

    private static function isNoisePlaceholder(string $segment): bool
    {
        $normalized = mb_strtolower($segment);

        return (bool) preg_match('/^(y\s*)?otros\s+demandados\.?\.?$/u', $normalized)
            || (bool) preg_match('/^otros\s+demandados/u', $normalized);
    }
}

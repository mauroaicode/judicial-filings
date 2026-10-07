<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Support;

/**
 * Extracts previous/new reporting-judge names from judicial "Cambio de ponente" annotations.
 */
final class SpeakerChangeAnnotationParser
{
    /**
     * @return array{previous: string|null, new: string|null}|null
     */
    public static function parse(?string $action, ?string $annotation): ?array
    {
        if (! self::looksLikeSpeakerChange($action, $annotation)) {
            return null;
        }

        $text = trim((string) $annotation);
        if ($text === '') {
            return null;
        }

        $new = self::matchValue($text, '/Ponente\s+nuevo\s*:\s*(.+)$/iu');
        if ($new === null) {
            return null;
        }

        $previous = self::matchValue(
            $text,
            '/Ponente\s+anterior\s*:\s*(.+?)(?=\s*Ponente\s+nuevo\s*:|$)/iu',
        );

        return [
            'previous' => $previous,
            'new' => $new,
        ];
    }

    public static function looksLikeSpeakerChange(?string $action, ?string $annotation): bool
    {
        $actionText = trim((string) $action);
        if ($actionText !== '' && preg_match('/cambio\s+de\s+ponente/iu', $actionText) === 1) {
            return true;
        }

        $annotationText = trim((string) $annotation);

        return $annotationText !== ''
            && preg_match('/Ponente\s+nuevo\s*:/iu', $annotationText) === 1;
    }

    private static function matchValue(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        $value = self::normalizeName($matches[1] ?? '');

        return $value !== '' ? $value : null;
    }

    private static function normalizeName(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = rtrim($value, " \t\n\r\0\x0B.-;");

        return trim($value);
    }
}

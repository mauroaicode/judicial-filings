<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

class StrParseHelper
{
    /**
     * Strip Latin diacritics (á→a) but keep ñ/Ñ (not treated as a tilde to drop).
     */
    public static function stripAccentsKeepEnie(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        // Protect ñ before ASCII transliteration (Str::ascii turns ñ → n).
        $protected = str_replace(
            ['ñ', 'Ñ'],
            ['__enie_lower__', '__enie_upper__'],
            $trimmed,
        );
        $ascii = \Illuminate\Support\Str::ascii($protected);

        return str_replace(
            ['__enie_lower__', '__enie_upper__'],
            ['ñ', 'Ñ'],
            $ascii,
        );
    }

    /**
     * Normalize imported labels: remove accents (keep ñ) + title case.
     * Empty input stays empty string (unlike {@see toTitleCase} which returns null).
     */
    public static function normalizeImportedLabel(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $stripped = self::stripAccentsKeepEnie($text) ?? '';
        if ($stripped === '') {
            return '';
        }

        return self::toTitleCase($stripped) ?? '';
    }

    /**
     * Convert a string to title case (first letter of each word capitalized).
     * Handles special cases like "DE", "Y", "DEL", etc. that should remain lowercase.
     * Preserves abbreviations like "S.A.", "S.A.S.", "LTDA.", etc.
     *
     * @param  string|null  $text  The text to convert.
     * @return string|null The converted text or null if input is null.
     */
    public static function toTitleCase(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        $trimmedText = trim($text);

        if ($trimmedText === '' || $trimmedText === '0') {
            return null;
        }

        // Words that should remain lowercase (except if they're the first word)
        $lowercaseWords = config('string-parser.lowercase_words', []);

        // Abbreviations that should remain uppercase
        $abbreviations = config('string-parser.abbreviations', []);

        // Split by spaces and process each word
        $words = explode(' ', $trimmedText);
        $result = [];

        foreach ($words as $index => $word) {
            $trimmedWord = trim($word);
            if ($trimmedWord === '') {
                continue;
            }

            if ($trimmedWord === '0') {
                continue;
            }

            $lowerWord = mb_strtolower($trimmedWord);

            // Check if it's an abbreviation (with or without dot)
            $isAbbreviation = false;
            foreach ($abbreviations as $abbr) {
                if ($lowerWord === $abbr || $lowerWord === rtrim((string) $abbr, '.')) {
                    $isAbbreviation = true;
                    // Preserve the original format but ensure uppercase
                    $result[] = mb_strtoupper($trimmedWord);
                    break;
                }
            }

            if ($isAbbreviation) {
                continue;
            }

            // If it's the first word or not in lowercase list, capitalize first letter
            if ($index === 0 || ! in_array($lowerWord, $lowercaseWords, true)) {
                $result[] = \Illuminate\Support\Str::ucfirst($lowerWord);

                continue;
            }

            // Keep lowercase words lowercase
            $result[] = $lowerWord;
        }

        return implode(' ', $result);
    }

    public static function buildAiTenantId(string $slug, string $id): string
    {
        return $slug.'_'.$id;
    }
}

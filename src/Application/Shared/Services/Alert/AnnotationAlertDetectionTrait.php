<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Alert;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Src\Domain\Process\Models\AlertActionKeyword;

trait AnnotationAlertDetectionTrait
{
    /**
     * Only keep fragments that map to an allowed keyword (Consulta, Apelación, Sentencia, etc.).
     *
     * @param  array<int, string>  $fragments
     * @return array<int, string>
     */
    protected function filterAllowedFragments(array $fragments): array
    {
        try {
            if (! Schema::hasTable('alert_actions_keywords')) {
                return $this->filterAllowedFragmentsFromConfig($fragments);
            }

            $allowed = [];
            foreach ($fragments as $fragment) {
                if (AlertActionKeyword::matchFragment($fragment) instanceof AlertActionKeyword) {
                    $allowed[] = $fragment;
                }
            }

            return $allowed;
        } catch (\Throwable) {
            return $this->filterAllowedFragmentsFromConfig($fragments);
        }
    }

    /**
     * @param  array<int, string>  $fragments
     * @return array<int, string>
     */
    protected function filterAllowedFragmentsFromConfig(array $fragments): array
    {
        $keywords = config('alert-keywords.keywords', []);
        $allowed = [];
        foreach ($fragments as $fragment) {
            $norm = $this->normalizeFragment($fragment);
            foreach ($keywords as $keyword) {
                $kwNorm = $this->normalizeFragment((string) $keyword);
                if ($norm === $kwNorm || mb_strpos($norm, $kwNorm) !== false) {
                    $allowed[] = $fragment;
                    break;
                }
            }
        }

        return $allowed;
    }

    protected function normalizeFragment(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = Str::ascii($s);
        $s = preg_replace('/\s+/u', ' ', (string) $s);

        return trim($s);
    }

    /**
     * @param  array<int, string>  $fragments
     * @return array<int, array{start: int, end: int, text: string}>
     */
    protected function findSpansInAnnotation(string $fullText, array $fragments): array
    {
        $spans = [];
        $usedRanges = [];
        $textLen = mb_strlen($fullText);

        foreach ($fragments as $fragment) {
            $fragment = trim($fragment);
            if ($fragment === '') {
                continue;
            }

            $pos = 0;
            $len = mb_strlen($fragment);

            while ($pos < $textLen) {
                $found = mb_stripos($fullText, $fragment, $pos);
                if ($found === false) {
                    break;
                }

                $end = $found + $len;
                $overlaps = false;
                foreach ($usedRanges as [$s, $e]) {
                    if ($end > $s && $found < $e) {
                        $overlaps = true;
                        break;
                    }
                }

                if (! $overlaps) {
                    $actualText = mb_substr($fullText, $found, $len);
                    $spans[] = [
                        'start' => $found,
                        'end' => $end,
                        'text' => $actualText,
                    ];
                    $usedRanges[] = [$found, $end];
                }

                $pos = $end;
            }
        }

        usort($spans, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $spans;
    }

    /**
     * @return array<int, array{start: int, end: int, text: string}>
     */
    protected function fallbackGetDetectedAlertSpans(string $text): array
    {
        $keywords = config('alert-keywords.keywords', [
            'Consulta',
            'Apelación',
            'Sentencia',
            'Rechaza',
            'Traslado',
            'Fijación estado',
            'Notificación estado',
        ]);
        $spans = [];

        foreach ($keywords as $keyword) {
            $keyword = (string) $keyword;
            $pos = 0;
            $len = mb_strlen($keyword);
            $textLen = mb_strlen($text);

            while ($pos < $textLen) {
                $found = mb_stripos($text, $keyword, $pos);
                if ($found === false) {
                    break;
                }

                $end = $found + $len;
                $spans[] = [
                    'start' => $found,
                    'end' => $end,
                    'text' => mb_substr($text, $found, $len),
                ];
                $pos = $end;
            }
        }

        usort($spans, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $spans;
    }
}

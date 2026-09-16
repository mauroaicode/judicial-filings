<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Collection;
use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Process\Models\ProcessAction;

class ProcessActionKeywordDetectionService
{
    private const SIMILARITY_MIN_CODE = 85.0;

    public function __construct() {}

    /**
     * Analyze a judicial action and return keywords with their match positions.
     *
     * @param  Collection<int, Keyword>  $keywords
     * @return Collection<int, array{keyword: Keyword, matches: array<array{start: int, end: int, text: string, source: string}>}>
     */
    public function handle(ProcessAction $action, Collection $keywords): Collection
    {
        $anno = $action->annotation ?? '';
        $act = $action->action ?? '';

        $combined = trim($anno.' '.$act);
        $boundary = mb_strlen($anno) + ($anno !== '' && $act !== '' ? 1 : 0);

        if ($combined === '' || $combined === '0') {
            return collect();
        }

        $results = collect();

        foreach ($keywords as $keywordModel) {
            $keywordText = $keywordModel->keyword;
            $matches = $this->findMatches($combined, $keywordText, $boundary);

            if ($matches !== []) {
                $results->push([
                    'keyword' => $keywordModel,
                    'matches' => $matches,
                ]);
            }
        }

        return $results;
    }

    /**
     * Find all matches and calculate their offsets in the combined text.
     *
     * Single-word keywords match a token (or a close fuzzy token).
     * Multi-word keywords match the full phrase across consecutive tokens
     * (e.g. "Audiencia Inicial" inside "Auto Convoca Audiencia Inicial").
     *
     * @param  int  $boundary  Offset where annotation ends and action text starts.
     * @return array<int, array{start: int, end: int, text: string, source: string}>
     */
    private function findMatches(string $combinedText, string $keyword, int $boundary): array
    {
        $keywordTokens = $this->tokenize($keyword);

        if ($keywordTokens === []) {
            return [];
        }

        $textTokens = $this->tokenizeWithOffsets($combinedText);

        if ($textTokens === []) {
            return [];
        }

        $window = count($keywordTokens);
        $matches = [];

        for ($i = 0; $i <= count($textTokens) - $window; $i++) {
            $slice = array_slice($textTokens, $i, $window);

            if (! $this->tokensMatch($slice, $keywordTokens)) {
                continue;
            }

            $start = $slice[0]['start'];
            $end = $slice[$window - 1]['end'];
            $text = mb_substr($combinedText, $start, $end - $start);
            $matches[] = $this->buildMatch($start, $text, $boundary);
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $normalized = $this->normalizeToken($part);

            if ($normalized !== '') {
                $tokens[] = $normalized;
            }
        }

        return $tokens;
    }

    /**
     * @return list<array{start: int, end: int, norm: string}>
     */
    private function tokenizeWithOffsets(string $text): array
    {
        if (! preg_match_all('/\S+/u', $text, $found, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $tokens = [];

        foreach ($found[0] as [$word, $byteOffset]) {
            $normalized = $this->normalizeToken($word);

            if ($normalized === '') {
                continue;
            }

            $start = mb_strlen(substr($text, 0, $byteOffset), 'UTF-8');
            $tokens[] = [
                'start' => $start,
                'end' => $start + mb_strlen($word, 'UTF-8'),
                'norm' => $normalized,
            ];
        }

        return $tokens;
    }

    /**
     * @param  list<array{start: int, end: int, norm: string}>  $slice
     * @param  list<string>  $keywordTokens
     */
    private function tokensMatch(array $slice, array $keywordTokens): bool
    {
        if (count($keywordTokens) === 1) {
            $word = $slice[0]['norm'];
            $keyword = $keywordTokens[0];

            if ($word === $keyword) {
                return true;
            }

            similar_text($word, $keyword, $percent);

            return $percent >= self::SIMILARITY_MIN_CODE;
        }

        foreach ($slice as $index => $token) {
            if ($token['norm'] !== $keywordTokens[$index]) {
                return false;
            }
        }

        return true;
    }

    private function normalizeToken(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $text) ?? '';
    }

    /**
     * Build match metadata with start/end offsets and source location.
     *
     * @return array{start: int, end: int, text: string, source: string}
     */
    private function buildMatch(int $start, string $text, int $boundary): array
    {
        $end = $start + mb_strlen($text);

        return [
            'start' => $start,
            'end' => $end,
            'text' => $text,
            'source' => $this->computeSource($start, $end, $boundary),
        ];
    }

    /**
     * Determine if match is in annotation, action, or both based on boundary.
     */
    private function computeSource(int $start, int $end, int $boundary): string
    {
        if ($boundary <= 0) {
            return 'action';
        }

        if ($end <= $boundary) {
            return 'annotation';
        }

        if ($start >= $boundary) {
            return 'action';
        }

        return 'both';
    }
}

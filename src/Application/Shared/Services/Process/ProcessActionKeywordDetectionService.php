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
     * @param  int  $boundary  Offset where annotation ends and action text starts.
     * @return array<int, array{start: int, end: int, text: string, source: string}>
     */
    private function findMatches(string $combinedText, string $keyword, int $boundary): array
    {
        $matches = [];
        $normText = $this->normalizeForSearch($combinedText);
        $normKeyword = $this->normalize($keyword);

        if ($normKeyword === '') {
            return [];
        }

        // Simple check for existence first
        if (mb_strpos($normText, $normKeyword) === false) {
            return [];
        }

        // We still need the original offsets, so we loop words but better
        $words = explode(' ', $combinedText);
        $currentOffset = 0;

        foreach ($words as $word) {
            $normWord = $this->normalize($word);
            $wordLen = mb_strlen($word);

            if ($normWord === $normKeyword) {
                $matches[] = $this->buildMatch($currentOffset, $word, $boundary);
            } else {
                similar_text($normWord, $normKeyword, $percent);
                if ($percent >= self::SIMILARITY_MIN_CODE) {
                    $matches[] = $this->buildMatch($currentOffset, $word, $boundary);
                }
            }

            $currentOffset += $wordLen + 1;
        }

        return $matches;
    }

    private function normalizeForSearch(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $replacements = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'];
        $text = strtr($text, $replacements);

        return preg_replace('/[^a-z0-9 ]/', ' ', $text); // Keep spaces as space
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

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $replacements = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'];
        $text = strtr($text, $replacements);

        return preg_replace('/[^a-z0-9 ]/', '', $text); // Keep spaces
    }
}

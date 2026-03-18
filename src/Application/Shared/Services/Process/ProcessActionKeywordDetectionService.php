<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Alert\AnnotationAlertDetectionInterface;
use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Process\Models\ProcessAction;

class ProcessActionKeywordDetectionService
{
    private const SIMILARITY_MIN_CODE = 85.0;
    private const SIMILARITY_MIN_AI = 60.0;

    public function __construct(
        private readonly AnnotationAlertDetectionInterface $aiDetector
    ) {}

    /**
     * Analyze a judicial action and return keywords with their match positions.
     *
     * @param ProcessAction $action
     * @param Collection<int, Keyword> $keywords
     * @return Collection<int, array{keyword: Keyword, matches: array<array{start: int, end: int, text: string, source: string}>}>
     */
    public function handle(ProcessAction $action, Collection $keywords): Collection
    {
        $anno = $action->annotation ?? '';
        $act = $action->action ?? '';
        
        $combined = trim($anno . ' ' . $act);
        $boundary = mb_strlen($anno) + ($anno !== '' && $act !== '' ? 1 : 0);

        if (empty($combined)) {
            return collect();
        }

        $results = collect();

        foreach ($keywords as $keywordModel) {
            $keywordText = $keywordModel->keyword;
            $matches = $this->findMatches($combined, $keywordText, $boundary);

            if (!empty($matches)) {
                $results->push([
                    'keyword' => $keywordModel,
                    'matches' => $matches
                ]);
            }
        }

        return $results;
    }

    /**
     * Find all matches and calculate their offsets in the combined text.
     *
     * @param string $combinedText
     * @param string $keyword
     * @param int $boundary Offset where annotation ends and action text starts.
     * @return array<int, array{start: int, end: int, text: string, source: string}>
     */
    private function findMatches(string $combinedText, string $keyword, int $boundary): array
    {
        $matches = [];
        $normCombined = $this->normalize($combinedText);
        $normKeyword = $this->normalize($keyword);

        // 1. Detección Exacta (Case insensitive / Accent insensitive)
        // Usamos la versión normalizada para encontrar offsets, pero recordamos que normalizar puede cambiar longitudes
        // Por eso buscaremos en el texto original usando Regex con soporte de acentos si es posible, 
        // o mapeando palabras. Para máxima fidelidad:
        
        $originalWords = explode(' ', $combinedText);
        $currentOffset = 0;

        foreach ($originalWords as $word) {
            $normWord = $this->normalize($word);
            $wordLen = mb_strlen($word);

            // Exacto
            if ($normWord === $normKeyword) {
                $matches[] = $this->buildMatch($currentOffset, $word, $boundary);
            } 
            // Fuzzy por código
            else {
                similar_text($normWord, $normKeyword, $percent);
                if ($percent >= self::SIMILARITY_MIN_CODE) {
                    $matches[] = $this->buildMatch($currentOffset, $word, $boundary);
                }
                // IA as last resort (only if enabled in config)
                elseif ($percent >= self::SIMILARITY_MIN_AI) {
                    if (config('ia-rag.keyword_detection_enabled', false) && $this->consultAiAsUmpire($word, $keyword)) {
                        $matches[] = $this->buildMatch($currentOffset, $word, $boundary);
                    }
                }
            }

            $currentOffset += $wordLen + 1; // +1 por el espacio
        }

        return $matches;
    }

    /**
     * Build match metadata with start/end offsets and source location.
     *
     * @param int $start
     * @param string $text
     * @param int $boundary
     * @return array{start: int, end: int, text: string, source: string}
     */
    private function buildMatch(int $start, string $text, int $boundary): array
    {
        $end = $start + mb_strlen($text);
        return [
            'start' => $start,
            'end' => $end,
            'text' => $text,
            'source' => $this->computeSource($start, $end, $boundary)
        ];
    }

    /**
     * Determine if match is in annotation, action, or both based on boundary.
     *
     * @param int $start
     * @param int $end
     * @param int $boundary
     * @return string
     */
    private function computeSource(int $start, int $end, int $boundary): string
    {
        if ($boundary <= 0) return 'action';
        if ($end <= $boundary) return 'annotation';
        if ($start >= $boundary) return 'action';
        return 'both';
    }

    /**
     * Perform deep normalization: lowercase, remove accents, remove non-alphanumeric characters.
     *
     * @param string $text
     * @return string
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $replacements = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
        $text = strtr($text, $replacements);
        return preg_replace('/[^a-z0-9]/', '', $text); // Muy estricto para comparación
    }

    /**
     * Use AI as an arbiter to decide if a word is a typo/variant of a keyword.
     *
     * @param string $word
     * @param string $keyword
     * @return bool
     */
    private function consultAiAsUmpire(string $word, string $keyword): bool
    {
        try {
            $prompt = "Judicial context. Is the word \"{$word}\" a typo or variant of \"{$keyword}\"? Answer only YES or NO.";
            $response = $this->aiDetector->getDetectedAlertSpans($prompt);
            return !empty($response);
        } catch (\Throwable) {
            return false;
        }
    }
}

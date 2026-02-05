<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Alert;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Alert\AnnotationAlertDetectionInterface;

class OpenAIAnnotationAlertDetectionProvider implements AnnotationAlertDetectionInterface
{
    public function containsAlertKeywords(string $annotation): bool
    {
        return $this->getDetectedAlertSpans($annotation) !== [];
    }

    /**
     * @return array<int, array{start: int, end: int, text: string}>
     */
    public function getDetectedAlertSpans(string $annotation): array
    {
        if ($annotation === '') {
            return [];
        }

        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('OpenAI API key not configured for alert detection');

            return $this->fallbackGetDetectedAlertSpans($annotation);
        }

        try {
            $fragments = $this->fetchFragmentsFromOpenAI($annotation);
            if ($fragments === null) {
                return $this->fallbackGetDetectedAlertSpans($annotation);
            }

            if ($fragments === []) {
                return [];
            }

            return $this->findSpansInAnnotation($annotation, $fragments);
        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Alert detection provider error', [
                    'message' => $e->getMessage(),
                ]);

            return $this->fallbackGetDetectedAlertSpans($annotation);
        }
    }

    /**
     * @param  array<int, string>  $fragments
     * @return array<int, array{start: int, end: int, text: string}>
     */
    private function findSpansInAnnotation(string $annotation, array $fragments): array
    {
        $spans = [];
        $usedRanges = [];

        foreach ($fragments as $fragment) {
            $fragment = trim($fragment);
            if ($fragment === '') {
                continue;
            }

            $pos = 0;
            $len = mb_strlen($fragment);
            $annotationLen = mb_strlen($annotation);

            while ($pos < $annotationLen) {
                $found = mb_stripos($annotation, $fragment, $pos);
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
                    $actualText = mb_substr($annotation, $found, $len);
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
     * @return array<int, string>|null List of fragments, or null when response format is unparseable (caller should use fallback).
     */
    private function fetchFragmentsFromOpenAI(string $annotation): ?array
    {
        $prompt = str_replace(
            ':annotation',
            $annotation,
            config('alert-ai.prompt_spans', config('alert-ai.prompt', 'Indica si el texto contiene palabras clave de alerta. Responde: No. O: Sí. Fragmentos: fragmento1, fragmento2. Texto: :annotation'))
        );

        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout(15)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('alert-ai.model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => config('alert-ai.temperature', 0),
                'max_tokens' => config('alert-ai.max_tokens_spans', 150),
            ]);

        if (! $response->successful()) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('OpenAI API error for alert detection', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            return null;
        }

        $body = $response->json();
        $content = trim($body['choices'][0]['message']['content'] ?? '');
        $upper = mb_strtoupper($content);

        if (str_contains($upper, 'NO') && ! str_contains($upper, 'SÍ') && ! str_contains($upper, 'SI')) {
            return [];
        }

        if (! str_contains($upper, 'FRAGMENTOS:') && ! str_contains($upper, 'PALABRAS:')) {
            return null;
        }

        $parts = preg_split('/\b(?:Fragmentos?|Palabras?)\s*:\s*/ui', $content, 2);
        $fragmentList = $parts[1] ?? $parts[0] ?? '';
        $fragmentList = preg_replace('/^(?:Sí|Si)\s*[.\s]*/ui', '', $fragmentList);

        $fragments = array_map(trim(...), preg_split('/[,;]+/u', (string) $fragmentList));
        $fragments = array_values(array_filter($fragments, static fn (string $s): bool => $s !== ''));

        // Trim trailing punctuation so "APELACIÓN." matches the word only in the annotation
        $fragments = array_map(
            static fn (string $s): string => preg_replace('/\p{P}+$/u', '', $s),
            $fragments,
        );

        return array_values(array_filter($fragments, static fn (string $s): bool => $s !== ''));
    }

    /**
     * @return array<int, array{start: int, end: int, text: string}>
     */
    private function fallbackGetDetectedAlertSpans(string $annotation): array
    {
        $keywords = config('alert-keywords.keywords', ['CONSULTA', 'APELACIÓN']);
        $spans = [];

        foreach ($keywords as $keyword) {
            $keyword = (string) $keyword;
            $pos = 0;
            $len = mb_strlen($keyword);
            $annotationLen = mb_strlen($annotation);

            while ($pos < $annotationLen) {
                $found = mb_stripos($annotation, $keyword, $pos);
                if ($found === false) {
                    break;
                }

                $end = $found + $len;
                $spans[] = [
                    'start' => $found,
                    'end' => $end,
                    'text' => mb_substr($annotation, $found, $len),
                ];
                $pos = $end;
            }
        }

        usort($spans, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $spans;
    }
}

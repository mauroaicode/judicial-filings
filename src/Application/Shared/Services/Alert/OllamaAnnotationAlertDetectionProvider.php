<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Alert;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Contracts\Alert\AnnotationAlertDetectionInterface;

class OllamaAnnotationAlertDetectionProvider implements AnnotationAlertDetectionInterface
{
    use AnnotationAlertDetectionTrait;

    public function containsAlertKeywords(string $text): bool
    {
        return $this->getDetectedAlertSpans($text) !== [];
    }

    /**
     * @return array<int, array{start: int, end: int, text: string}>
     */
    public function getDetectedAlertSpans(string $text): array
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) < 3) {
            return [];
        }

        $baseUrl = rtrim((string) config('alert-ai.ollama.base_url', 'http://127.0.0.1:11434'), '/');
        if ($baseUrl === '') {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Ollama base_url not configured for alert detection');

            return $this->fallbackGetDetectedAlertSpans($text);
        }

        try {
            $fragments = $this->fetchFragmentsFromOllama($text, $baseUrl);
            if ($fragments === null) {
                return $this->fallbackGetDetectedAlertSpans($text);
            }

            if ($fragments === []) {
                return [];
            }

            $fragments = $this->filterAllowedFragments($fragments);

            return $this->findSpansInAnnotation($text, $fragments);
        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Ollama alert detection provider error', [
                    'message' => $e->getMessage(),
                ]);

            return $this->fallbackGetDetectedAlertSpans($text);
        }
    }

    /**
     * Llama a Ollama POST /api/chat y parsea la respuesta como fragmentos.
     *
     * @return array<int, string>|null
     *
     * @throws ConnectionException
     */
    private function fetchFragmentsFromOllama(string $text, string $baseUrl): ?array
    {
        $words = config('alert-keywords.words', ['Consulta', 'Apelación', 'Sentencia', 'Rechaza', 'Traslado']);
        $phrases = config('alert-keywords.phrases', ['Fijación estado', 'Notificación estado']);
        $wordsList = implode(', ', $words);
        $phrasesList = '"'.implode('", "', $phrases).'"';

        $prompt = config('alert-ai.prompt_spans', 'Texto: :annotation');
        $prompt = str_replace([':words', ':phrases', ':annotation'], [$wordsList, $phrasesList, $text], $prompt);

        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        Log::channel($channel)->debug('Ollama alert detection request', [
            'text_length' => mb_strlen($text),
            'base_url' => $baseUrl,
        ]);

        $model = config('alert-ai.ollama.model', 'llama3.2:3b');
        $timeout = (int) config('alert-ai.ollama.timeout', 60);

        $response = Http::timeout($timeout)
            ->post("{$baseUrl}/api/chat", [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'stream' => false,
                'options' => [
                    'temperature' => config('alert-ai.temperature', 0),
                    'num_predict' => config('alert-ai.max_tokens_spans', 200),
                ],
            ]);

        if (! $response->successful()) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Ollama API error for alert detection', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            return null;
        }

        $body = $response->json();
        $content = trim($body['message']['content'] ?? '');
        $upper = mb_strtoupper($content);

        Log::channel($channel)->debug('Ollama alert detection response', [
            'response_preview' => mb_substr($content, 0, 300),
        ]);

        if (str_contains($upper, 'NO') && ! str_contains($upper, 'SÍ') && ! str_contains($upper, 'SI')) {
            Log::channel($channel)->debug('Ollama returned No - no keywords found');

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

        $fragments = array_map(
            static fn (string $s): string => preg_replace('/\p{P}+$/u', '', $s),
            $fragments,
        );

        return array_values(array_filter($fragments, static fn (string $s): bool => $s !== ''));
    }
}

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
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('OpenAI API key not configured for alert detection');

            return $this->fallbackContainsAlertKeywords($annotation);
        }

        try {
            $prompt = str_replace(
                ':annotation',
                $annotation,
                config('alert-ai.prompt', 'Indica si el siguiente texto contiene alguna palabra clave de alerta (responde solo sí o no). Texto: :annotation')
            );

            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('alert-ai.model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => config('alert-ai.temperature', 0),
                    'max_tokens' => config('alert-ai.max_tokens', 10),
                ]);

            if (! $response->successful()) {
                Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                    ->error('OpenAI API error for alert detection', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                return $this->fallbackContainsAlertKeywords($annotation);
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? '';
            $normalized = mb_strtoupper(trim($content));

            return str_contains($normalized, 'SÍ') || str_contains($normalized, 'SI') || str_contains($normalized, 'YES');
        } catch (\Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('Alert detection provider error', [
                    'message' => $e->getMessage(),
                ]);

            return $this->fallbackContainsAlertKeywords($annotation);
        }
    }

    /**
     * Fallback: check annotation against config keywords when API is unavailable.
     */
    private function fallbackContainsAlertKeywords(string $annotation): bool
    {
        $keywords = config('alert-keywords.keywords', ['CONSULTA', 'APELACIÓN']);
        $upper = mb_strtoupper($annotation);

        foreach ($keywords as $keyword) {
            if (str_contains($upper, mb_strtoupper((string) $keyword))) {
                return true;
            }
        }

        return false;
    }
}

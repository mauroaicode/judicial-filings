<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Support;

final class RagQueryStreamBodyHelper
{
    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array<string, mixed>
     */
    public static function build(
        string $sessionId,
        string $query,
        string $mode,
        string $source,
        string $responseType,
        array $history,
        ?string $userPrompt = null,
    ): array {
        $body = [
            'query' => $query,
            'mode' => $mode,
            'source' => $source,
            'response_type' => $responseType,
            'session_id' => $sessionId,
            'enable_memory' => (bool) config('ai-chat.enable_memory', true),
        ];

        if ($userPrompt !== null && $userPrompt !== '') {
            $body['user_prompt'] = $userPrompt;
        }

        if ($history !== []) {
            $body['conversation_history'] = $history;
        }

        return $body;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Services;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Src\Application\AppUser\AiChat\Data\SendVoiceMessageData;
use Src\Application\AppUser\AiChat\Jobs\UpdateAiChatTitleJob;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AiChat\Models\AiChatMessage;

readonly class AiChatVoiceService
{
    /**
     * @return array{answer: string, user_message_id: string, assistant_message_id: string}
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function handle(AiChat $chat, SendVoiceMessageData $data): array
    {
        $history = $this->getChatHistory($chat);

        $userMessage = $this->saveUserMessage($chat, $data);

        $ragOptions = $this->prepareRagRequestData($chat);

        $body = [
            'query' => $data->content,
            'mode' => $ragOptions['mode'],
            'response_type' => config('ai-chat.voice_response_type', 'paragraph'),
            'user_prompt' => $ragOptions['user_prompt'],
        ];

        if ($history !== []) {
            $body['conversation_history'] = $history;
        }

        $timeout = (int) config('ia-rag.timeout');

        $response = Http::timeout($timeout)
            ->post($ragOptions['url'], $body);

        if (! $response->successful()) {
            throw new Exception('RAG API query failed: '.$response->body());
        }

        $answer = (string) ($response->json('answer') ?? '');

        if ($answer === '') {
            throw new Exception('RAG API returned an empty answer.');
        }

        $assistantMessage = $this->saveAssistantMessage($chat, $answer);

        return [
            'answer' => $answer,
            'user_message_id' => (string) $userMessage->id,
            'assistant_message_id' => (string) $assistantMessage->id,
        ];
    }

    private function saveUserMessage(AiChat $chat, SendVoiceMessageData $data): AiChatMessage
    {
        /** @var AiChatMessage $message */
        $message = AiChatMessage::query()->create([
            'ai_chat_id' => $chat->id,
            'role' => 'user',
            'search_mode' => null,
            'content' => $data->content,
        ]);

        if ($chat->messages()->where('role', 'user')->count() === 1) {
            dispatch(new UpdateAiChatTitleJob($chat, $data->content));
        }

        return $message;
    }

    private function saveAssistantMessage(AiChat $chat, string $content): AiChatMessage
    {
        /** @var AiChatMessage $message */
        $message = AiChatMessage::query()->create([
            'ai_chat_id' => $chat->id,
            'role' => 'assistant',
            'search_mode' => null,
            'content' => $content,
        ]);

        return $message;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function getChatHistory(AiChat $chat): array
    {
        /** @var Collection<int, AiChatMessage> $messages */
        $messages = $chat->messages()->latest()
            ->limit(10)
            ->get();

        return $messages->reverse()
            ->values()
            ->map(fn (AiChatMessage $msg): array => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->all();
    }

    /**
     * @return array{url: string, mode: string, user_prompt: string}
     */
    private function prepareRagRequestData(AiChat $chat): array
    {
        /** @var \Src\Domain\Process\Models\Process $process */
        $process = $chat->process;

        $tenantId = 'abogados_9ab2a17f-7f13-431a-b57c-efb60d49fd5d';
        $url = config('ia-rag.base_url').'/query?tenant_id='.$tenantId;

        $mode = 'naive';

        $userPrompt = $this->buildVoiceUserPrompt($process->process_number);

        return [
            'url' => $url,
            'mode' => $mode,
            'user_prompt' => $userPrompt,
        ];
    }

    private function buildVoiceUserPrompt(string $processNumber): string
    {
        $base = str_replace(
            '{process_number}',
            $processNumber,
            config('ai-chat.voice_prompt_template', '')
        );

        $tags = (string) config('ai-chat.voice_tts_tags_instructions', '');
        $prompt = trim($tags === '' ? $base : $base.' '.$tags);

        $maxLength = (int) config('ai-chat.voice_user_prompt_max_length', 1000);

        if (strlen($prompt) > $maxLength) {
            $prompt = substr($prompt, 0, $maxLength);
        }

        return $prompt;
    }
}

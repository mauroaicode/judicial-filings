<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Src\Application\AppUser\AiChat\Data\SendVoiceMessageData;
use Src\Application\AppUser\AiChat\Jobs\UpdateAiChatTitleJob;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AiChat\Models\AiChatMessage;
use Src\Domain\Process\Models\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class AiChatVoiceService
{
    public function handle(AiChat $chat, SendVoiceMessageData $data): StreamedResponse
    {
        $history = $this->getChatHistory($chat);

        $this->saveUserMessage($chat, $data);

        $ragOptions = $this->prepareRagRequestData($chat);

        return new StreamedResponse(function () use ($chat, $data, $history, $ragOptions): void {
            try {
                $client = new Client;

                $body = [
                    'query' => $data->content,
                    'mode' => $ragOptions['mode'],
                    'source' => $ragOptions['source'],
                    'response_type' => config('ai-chat.voice_response_type', 'paragraph'),
                ];

                if ($ragOptions['user_prompt'] !== '') {
                    $body['user_prompt'] = $ragOptions['user_prompt'];
                }

                if ($history !== []) {
                    $body['conversation_history'] = $history;
                }

                Log::info('AI voice → rag-api stream request', [
                    'ai_chat_id' => $chat->id,
                    'url' => $ragOptions['url'],
                    'body' => $body,
                    'history_count' => count($history),
                ]);

                $response = $client->post($ragOptions['url'], [
                    'json' => $body,
                    'stream' => true,
                    'headers' => ['Accept' => 'text/event-stream'],
                ]);

                $stream = $response->getBody();
                $fullResponseContent = '';

                while (! $stream->eof()) {
                    $chunk = $stream->read(1024);
                    echo $chunk;

                    $fullResponseContent .= $this->extractContentFromChunk($chunk);

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                }

                if ($fullResponseContent !== '' && $fullResponseContent !== '0') {
                    $this->saveAssistantMessage($chat, $fullResponseContent);
                }

            } catch (ClientException $e) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                Log::error('AI voice RAG API validation error (422): '.$responseBody);
                echo 'data: '.json_encode(['error' => 'Error de validación en el motor de IA: '.$responseBody])."\n\n";
            } catch (\Throwable $e) {
                Log::error('AI voice RAG API error: '.$e->getMessage());
                echo 'data: '.json_encode(['error' => 'Error de conexión con el motor de IA'])."\n\n";
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function saveUserMessage(AiChat $chat, SendVoiceMessageData $data): void
    {
        AiChatMessage::query()->create([
            'ai_chat_id' => $chat->id,
            'role' => 'user',
            'search_mode' => null,
            'content' => $data->content,
        ]);

        if ($chat->messages()->where('role', 'user')->count() === 1) {
            dispatch(new UpdateAiChatTitleJob($chat, $data->content));
        }
    }

    private function saveAssistantMessage(AiChat $chat, string $content): void
    {
        AiChatMessage::query()->create([
            'ai_chat_id' => $chat->id,
            'role' => 'assistant',
            'search_mode' => null,
            'content' => $content,
        ]);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function getChatHistory(AiChat $chat): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, AiChatMessage> $messages */
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

    private function extractContentFromChunk(string $chunk): string
    {
        $content = '';
        $lines = explode("\n", $chunk);
        foreach ($lines as $line) {
            if (str_starts_with($line, 'data: ')) {
                $jsonData = json_decode(substr($line, 6), true);
                if (isset($jsonData['chunk'])) {
                    $content .= $jsonData['chunk'];
                }
            }
        }

        return $content;
    }

    /**
     * @return array{url: string, mode: string, source: string, user_prompt: string}
     */
    private function prepareRagRequestData(AiChat $chat): array
    {
        /** @var Process $process */
        $process = $chat->process;

        $tenantId = 'abogados_9ab2a17f-7f13-431a-b57c-efb60d49fd5d';
        $url = config('ia-rag.base_url').'/query/stream?tenant_id='.$tenantId;

        $userPrompt = '';

        if (config('ai-chat.voice_send_user_prompt', true)) {
            $userPrompt = str_replace(
                '{process_number}',
                $process->process_number,
                config('ai-chat.voice_prompt_template', '')
            );
        }

        return [
            'url' => $url,
            'mode' => (string) config('ai-chat.voice_mode', 'auto'),
            'source' => (string) config('ai-chat.voice_source', 'voice'),
            'user_prompt' => $userPrompt,
        ];
    }
}

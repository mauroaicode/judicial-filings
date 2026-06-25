<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Src\Application\AppUser\AiChat\Data\SendMessageData;
use Src\Application\AppUser\AiChat\Jobs\UpdateAiChatTitleJob;
use Src\Application\AppUser\AiChat\Support\RagQueryStreamBodyHelper;
use Src\Application\AppUser\AiChat\Support\RagSseProxyHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AiChat\Models\AiChatMessage;
use Src\Domain\Process\Models\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class AiChatStreamService
{
    public function handle(AiChat $chat, SendMessageData $data, string $appUserId): StreamedResponse
    {
        // 1. Obtener historial ANTES de guardar el nuevo mensaje
        $history = $this->getChatHistory($chat);

        // 2. Guardar mensaje del usuario
        $this->saveUserMessage($chat, $data);

        $ragOptions = $this->prepareRagRequestData($chat, $data);

        return new StreamedResponse(function () use ($chat, $data, $history, $ragOptions, $appUserId): void {
            try {
                RagSseProxyHelper::flushKeepAlive();

                $client = new Client(RagSseProxyHelper::guzzleOptions());

                $body = RagQueryStreamBodyHelper::build(
                    sessionId: $appUserId,
                    query: $data->content,
                    mode: $ragOptions['mode'],
                    source: (string) config('ai-chat.chat_source', 'chat'),
                    responseType: config('ai-chat.response_type', 'paragraph'),
                    history: $history,
                    userPrompt: $ragOptions['user_prompt'],
                );

                $response = $client->post($ragOptions['url'], [
                    'json' => $body,
                    'stream' => true,
                    'headers' => ['Accept' => 'text/event-stream'],
                ]);

                $fullResponseContent = '';
                $sseBuffer = '';

                RagSseProxyHelper::relay(
                    $response->getBody(),
                    function (string $chunk) use (&$fullResponseContent, &$sseBuffer): void {
                        $fullResponseContent .= $this->extractContentFromSseChunk($chunk, $sseBuffer);
                    }
                );

                $fullResponseContent .= $this->flushSseBuffer($sseBuffer);

                if ($fullResponseContent !== '' && $fullResponseContent !== '0') {
                    $this->saveAssistantMessage($chat, $fullResponseContent, $data->search_mode);
                }

            } catch (ClientException $e) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                Log::error('RAG API Validation Error (422): '.$responseBody);
                echo 'data: '.json_encode(['error' => 'Error de validación en el motor de IA: '.$responseBody])."\n\n";
            } catch (\Throwable $e) {
                Log::error('RAG API Error: '.$e->getMessage());
                echo 'data: '.json_encode(['error' => 'Error de conexión con el motor de IA'])."\n\n";
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function saveUserMessage(AiChat $chat, SendMessageData $data): void
    {
        AiChatMessage::query()->create([
            'ai_chat_id' => $chat->id,
            'role' => 'user',
            'search_mode' => $data->search_mode,
            'content' => $data->content,
        ]);

        if ($chat->messages()->where('role', 'user')->count() === 1) {
            dispatch(new UpdateAiChatTitleJob($chat, $data->content));
        }

    }

    private function saveAssistantMessage(AiChat $chat, string $content, string $mode): void
    {
        AiChatMessage::query()->create([
            'ai_chat_id' => $chat->id,
            'role' => 'assistant',
            'search_mode' => $mode,
            'content' => $content,
        ]);
    }

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

    private function extractContentFromSseChunk(string $chunk, string &$sseBuffer): string
    {
        $sseBuffer .= $chunk;
        $lines = explode("\n", $sseBuffer);
        $sseBuffer = array_pop($lines);

        return $this->extractContentFromSseLines($lines);
    }

    private function flushSseBuffer(string &$sseBuffer): string
    {
        if ($sseBuffer === '') {
            return '';
        }

        $lines = [$sseBuffer];
        $sseBuffer = '';

        return $this->extractContentFromSseLines($lines);
    }

    /**
     * Text chat may stream incremental "chunk" events and/or a final "answer" on done.
     *
     * @param  list<string>  $lines
     */
    private function extractContentFromSseLines(array $lines): string
    {
        $content = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! str_starts_with($line, 'data: ')) {
                continue;
            }

            $jsonData = json_decode(substr($line, 6), true);
            if (! is_array($jsonData)) {
                continue;
            }

            if (isset($jsonData['chunk']) && is_string($jsonData['chunk'])) {
                $content .= $jsonData['chunk'];

                continue;
            }

            if (isset($jsonData['answer']) && is_string($jsonData['answer'])) {
                $content .= $jsonData['answer'];
            }
        }

        return $content;
    }

    /**
     * @return array{url: string, mode: string, user_prompt: string}
     */
    private function prepareRagRequestData(AiChat $chat, SendMessageData $data): array
    {
        /** @var Process $process */
        $process = $chat->process;

        //        $tenantId = StrParseHelper::buildAiTenantId((string) $organization->slug, (string) $organization->id);
        $tenantId = 'abogados_9ab2a17f-7f13-431a-b57c-efb60d49fd5d';
        $url = config('ia-rag.base_url').'/query/stream?tenant_id='.$tenantId;

        $modeMapping = config('ai-chat.modes_mapping', []);
        $mode = $modeMapping[$data->search_mode] ?? 'naive';

        $userPrompt = str_replace(
            '{process_number}',
            $process->process_number,
            config('ai-chat.prompt_template')
        );

        return [
            'url' => $url,
            'mode' => $mode,
            'user_prompt' => $userPrompt,
        ];
    }
}

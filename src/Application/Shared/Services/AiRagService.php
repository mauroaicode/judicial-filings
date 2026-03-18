<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Exception;

class AiRagService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('ia-rag.base_url', 'http://localhost:8000');
    }

    /**
     * Upload process data in Markdown format to the RAG engine.
     * @throws ConnectionException
     * @throws Exception
     */
    public function uploadMarkdown(string $tenantId, string $docId, string $markdownContent): string
    {
        $queryParams = http_build_query([
            'tenant_id' => $tenantId,
            'doc_id' => $docId,
        ]);

        $timeout = (int) config('ia-rag.timeout');

        $response = Http::timeout($timeout)
            ->attach('file', $markdownContent, "process_{$docId}.md")
            ->post("{$this->baseUrl}/insert?{$queryParams}");

        if (!$response->successful()) {
            throw new Exception("DeepSeek-RAG upload failed: " . $response->body());
        }

        $taskId = $response->json('task_id');

        $this->waitForTask($taskId, $tenantId);

        return $taskId;
    }

    /**
     * Wait for a task to be completed.
     * @throws Exception
     */
    private function waitForTask(string $taskId, string $tenantId): void
    {
        $attempts = 0;
        $maxAttempts = (int) config('ia-rag.task_max_attempts');
        $delay = (int) config('ia-rag.task_retry_delay');
        $timeout = (int) config('ia-rag.timeout');

        while ($attempts < $maxAttempts) {
            $queryParams = http_build_query(['tenant_id' => $tenantId]);
            $response = Http::timeout($timeout)
                ->get("{$this->baseUrl}/task/{$taskId}?{$queryParams}");

            if ($response->successful() && $response->json('status') === 'completed') {
                return;
            }

            if ($response->json('status') === 'failed') {
                throw new Exception("DeepSeek-RAG task failed: " . $response->json('error', 'Unknown error'));
            }

            sleep($delay);
            $attempts++;
        }

        throw new Exception("DeepSeek-RAG task timeout for task: {$taskId}");
    }

    /**
     * Query the RAG engine for an AI summary.
     * @throws ConnectionException
     * @throws Exception
     */
    public function querySummary(string $tenantId, ?string $prompt = null): array
    {
        $queryPrompt = $prompt ?? config('ia-rag.prompts.summary');
        $queryParams = http_build_query(['tenant_id' => $tenantId]);

        $timeout = (int) config('ia-rag.timeout');

        $response = Http::timeout($timeout)
            ->post("{$this->baseUrl}/query?{$queryParams}", [
                'query' => $queryPrompt,
                'mode' => 'mix',
                'response_type' => 'json',
            ]);

        if (!$response->successful()) {
            throw new Exception("DeepSeek-RAG query failed: " . $response->body());
        }

        $data = $response->json();
        $answer = $data['answer'] ?? '';

        if (is_string($answer) && str_contains($answer, '```json')) {
            preg_match('/```json\s*(.*?)\s*```/s', $answer, $matches);
            if (isset($matches[1])) {
                $decoded = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Domain\AiChat\Models\AiChat;

class UpdateAiChatTitleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public AiChat $chat,
        public string $firstMessage
    ) {}

    public function handle(): void
    {
        // No actualizamos el título si ya fue personalizado o no es un título genérico
        // Pero en este caso, el requerimiento es generarlo basado en el primer mensaje.

        try {
            $url = config('ia-rag.base_url').'/api/v1/generate-title';

            $response = Http::timeout(10)->post($url, [
                'text' => $this->firstMessage,
            ]);

            if ($response->successful()) {
                $title = $response->json('title');
                if (! empty($title)) {
                    $this->chat->update(['title' => $title]);
                }
            } else {
                // Fallback simple si falla la IA: Truncar el primer mensaje
                $this->chat->update([
                    'title' => substr($this->firstMessage, 0, 50).'...',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Error generating chat title: '.$e->getMessage());
        }
    }
}

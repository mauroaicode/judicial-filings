<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Controllers;

use Illuminate\Http\Request;
use Src\Application\AppUser\AiChat\Data\SendMessageData;
use Src\Application\AppUser\AiChat\Services\AiChatStreamService;
use Src\Domain\AiChat\Models\AiChat;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class AiChatStreamController
{
    public function __construct(
        private AiChatStreamService $streamService
    ) {}

    public function __invoke(string $chatId, SendMessageData $data, Request $request): StreamedResponse
    {
        $appUser = $request->user();
        $organization = $appUser->organizations()->where('is_active', true)->firstOrFail();

        if (! $organization->is_ai_enabled) {
            abort(403, 'IA access is disabled for this organization.');
        }

        $chat = AiChat::query()
            ->with(['organization', 'process'])
            ->where('id', $chatId)
            ->where('organization_id', $organization->id)
            ->wherePublicOrTransitive($appUser->id)
            ->firstOrFail();

        return $this->streamService->handle($chat, $data, $appUser->id);
    }
}

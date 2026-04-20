<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Controllers;

use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;
use Src\Application\AppUser\AiChat\Data\AiChatMessageResource;
use Src\Application\AppUser\AiChat\Services\ListAiChatMessagesService;
use Src\Domain\AiChat\Models\AiChat;

class ListAiChatMessagesController
{
    public function __construct(
        private readonly ListAiChatMessagesService $service
    ) {}

    /**
     * @return DataCollection<AiChatMessageResource>
     */
    public function __invoke(string $chatId, Request $request): DataCollection
    {
        $appUser = $request->user();
        $organization = $appUser->organizations()->where('is_active', true)->firstOrFail();

        $chat = AiChat::query()
            ->where('id', $chatId)
            ->where('organization_id', $organization->id)
            ->wherePublicOrTransitive($appUser->id)
            ->firstOrFail();

        return $this->service->handle($chat);
    }
}

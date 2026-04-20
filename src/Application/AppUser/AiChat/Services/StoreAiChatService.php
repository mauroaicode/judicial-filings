<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\AiChat\Data\StoreAiChatData;
use Src\Domain\AiChat\Models\AiChat;

readonly class StoreAiChatService
{
    public function handle(StoreAiChatData $data, string $organizationId, string $appUserId): AiChat
    {
        /** @var AiChat $chat */
        $chat = DB::transaction(fn (): AiChat => $this->createChat($data, $organizationId, $appUserId));

        return $chat;
    }

    private function createChat(StoreAiChatData $data, string $organizationId, string $appUserId): AiChat
    {
        /** @var AiChat $chat */
        $chat = AiChat::query()->create([
            'organization_id' => $organizationId,
            'process_id' => $data->process_id,
            'app_user_id' => $appUserId,
            'title' => $data->title ?? __('process.initial_chat'),
            'is_private' => $data->is_private,
        ]);

        return $chat;
    }
}

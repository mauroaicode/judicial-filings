<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Services;

use Spatie\LaravelData\DataCollection;
use Src\Application\AppUser\AiChat\Data\AiChatMessageResource;
use Src\Domain\AiChat\Models\AiChat;

class ListAiChatMessagesService
{
    /**
     * @return DataCollection<AiChatMessageResource>
     */
    public function handle(AiChat $chat): DataCollection
    {
        return new DataCollection(
            AiChatMessageResource::class,
            $chat->messages()
                ->orderBy('created_at', 'asc')
                ->get()
        );
    }
}

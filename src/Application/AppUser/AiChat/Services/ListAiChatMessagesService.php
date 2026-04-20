<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Services;

use Spatie\LaravelData\DataCollection;
use Src\Application\AppUser\AiChat\Data\AiChatMessageData;
use Src\Domain\AiChat\Models\AiChat;

class ListAiChatMessagesService
{
    /**
     * @return DataCollection<AiChatMessageData>
     */
    public function handle(AiChat $chat): DataCollection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \Src\Domain\AiChat\Models\AiChatMessage> $messages */
        $messages = $chat->messages()->oldest()
            ->get();

        return new DataCollection(
            AiChatMessageData::class,
            $messages->all()
        );
    }
}

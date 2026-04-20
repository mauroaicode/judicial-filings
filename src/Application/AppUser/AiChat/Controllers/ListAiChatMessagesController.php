<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Controllers;

use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;
use Src\Application\AppUser\AiChat\Data\AiChatMessageData;
use Src\Application\AppUser\AiChat\Services\ListAiChatMessagesService;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

class ListAiChatMessagesController
{
    public function __construct(
        private readonly ListAiChatMessagesService $service
    ) {}

    /**
     * @return DataCollection<AiChatMessageData>
     */
    public function __invoke(string $chatId, Request $request): DataCollection
    {
        /** @var AppUser $appUser */
        $appUser = $request->user();

        /** @var Organization|null $organization */
        $organization = $appUser->organizations()->where('is_active', true)->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $chat = AiChat::query()
            ->where('id', $chatId)
            ->where('organization_id', $organization->id)
            ->wherePublicOrTransitive((string) $appUser->id)
            ->firstOrFail();

        /** @var AiChat $chat */
        return $this->service->handle($chat);
    }
}

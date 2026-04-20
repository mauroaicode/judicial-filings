<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Services;

use Illuminate\Support\Collection;
use Src\Domain\AiChat\Models\AiChat;

readonly class ListAiChatService
{
    /**
     * @return Collection<int, AiChat>
     */
    public function handle(string $organizationId, string $processId, string $appUserId): Collection
    {
        return $this->getChats($organizationId, $processId, $appUserId);
    }

    /**
     * @return Collection<int, AiChat>
     */
    private function getChats(string $organizationId, string $processId, string $appUserId): Collection
    {
        return AiChat::query()
            ->whereOrganization($organizationId)
            ->whereProcess($processId)
            ->whereActive()
            ->wherePublicOrTransitive($appUserId)
            ->orderedByRecent()
            ->get();
    }
}

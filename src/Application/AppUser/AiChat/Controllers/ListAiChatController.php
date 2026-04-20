<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\AiChat\Resources\AiChatResource;
use Src\Application\AppUser\AiChat\Services\ListAiChatService;
use Src\Domain\AppUser\Models\AppUser;

readonly class ListAiChatController
{
    public function __construct(
        private ListAiChatService $listAiChatService
    ) {}

    public function __invoke(string $processId): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $chats = $this->listAiChatService->handle($organization->id, $processId, $appUser->id);

        return response()->json(AiChatResource::collect($chats));
    }
}

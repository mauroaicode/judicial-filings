<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\AppUser\AiChat\Data\StoreAiChatData;
use Src\Application\AppUser\AiChat\Resources\AiChatResource;
use Src\Application\AppUser\AiChat\Services\StoreAiChatService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

readonly class StoreAiChatController
{
    public function __construct(
        private StoreAiChatService $storeAiChatService
    ) {}

    public function __invoke(Request $request, StoreAiChatData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = $request->user();

        /** @var Organization|null $organization */
        $organization = $appUser->organizations()->where('is_active', true)->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        if (! $organization->is_ai_enabled) {
            abort(403, 'IA access is disabled for this organization.');
        }

        $chat = $this->storeAiChatService->handle($data, $organization->id, (string) $appUser->id);

        return response()->json(AiChatResource::fromModel($chat), 201);
    }
}

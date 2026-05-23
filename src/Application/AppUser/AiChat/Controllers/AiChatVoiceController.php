<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Controllers;

use Illuminate\Http\Request;
use Src\Application\AppUser\AiChat\Data\SendVoiceMessageData;
use Src\Application\AppUser\AiChat\Services\AiChatVoiceService;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class AiChatVoiceController
{
    public function __construct(
        private AiChatVoiceService $voiceService
    ) {}

    public function __invoke(string $chatId, SendVoiceMessageData $data, Request $request): StreamedResponse
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

        $chat = AiChat::query()
            ->with(['organization', 'process'])
            ->where('id', $chatId)
            ->where('organization_id', $organization->id)
            ->wherePublicOrTransitive((string) $appUser->id)
            ->firstOrFail();

        /** @var AiChat $chat */
        return $this->voiceService->handle($chat, $data);
    }
}

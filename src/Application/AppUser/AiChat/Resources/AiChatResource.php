<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;

class AiChatResource extends Resource
{
    public function __construct(
        public string $id,
        public string $title,
        public bool $is_private,
        public string $created_at,
        public string $diff_for_humans,
        public ?string $app_user_name = null,
    ) {}

    public static function fromModel(AiChat $chat): self
    {
        /** @var AppUser|null $appUser */
        $appUser = $chat->appUser;

        return new self(
            id: (string) $chat->id,
            title: str_contains((string) $chat->title, 'process.') ? __((string) $chat->title) : (string) $chat->title,
            is_private: (bool) $chat->is_private,
            created_at: DateFormatHelper::formatDateTimeWithDayOfWeek($chat->created_at),
            diff_for_humans: $chat->created_at->diffForHumans(),
            app_user_name: $appUser ? $appUser->name.' '.$appUser->last_name : null,
        );
    }
}

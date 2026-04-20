<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\AiChat\Models\AiChat;

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
        return new self(
            id: $chat->id,
            title: $chat->title,
            is_private: $chat->is_private,
            created_at: DateFormatHelper::formatDateTimeWithDayOfWeek($chat->created_at),
            diff_for_humans: $chat->created_at->diffForHumans(),
            app_user_name: $chat->appUser?->name . ' ' . $chat->appUser?->last_name,
        );
    }
}

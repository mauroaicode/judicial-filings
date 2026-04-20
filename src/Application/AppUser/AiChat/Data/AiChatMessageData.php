<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class AiChatMessageData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public string $id,
        public string $role, // 'user' | 'assistant'
        public string $content,
        public string $created_at,
        public ?string $search_mode = null,
    ) {}

    public static function attributes(): array
    {
        return [
            'id' => __('data.id'),
            'role' => __('data.role'),
            'content' => __('data.content'),
            'created_at' => __('data.created_at'),
            'search_mode' => __('data.search_mode'),
        ];
    }
}

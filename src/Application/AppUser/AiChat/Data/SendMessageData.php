<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class SendMessageData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public string $content,
        public string $search_mode = 'agile', // 'agile' or 'strategic'
    ) {}

    public static function attributes(): array
    {
        return [
            'content' => __('data.content'),
            'search_mode' => __('data.search_mode'),
        ];
    }
}

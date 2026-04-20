<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class StoreAiChatData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public string $process_id,
        public ?string $title = null,
        public bool $is_private = false,
    ) {}

    public static function attributes(): array
    {
        return [
            'process_id' => __('data.process_id'),
            'title' => __('data.title'),
            'is_private' => __('data.is_private'),
        ];
    }
}

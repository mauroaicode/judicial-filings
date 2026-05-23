<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class SendVoiceMessageData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public string $content,
    ) {}

    public static function attributes(): array
    {
        return [
            'content' => __('data.content'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Resources;

use Spatie\LaravelData\Resource;

class AiChatVoiceResource extends Resource
{
    public function __construct(
        public string $answer,
        public string $user_message_id,
        public string $assistant_message_id,
    ) {}

    /**
     * @param  array{answer: string, user_message_id: string, assistant_message_id: string}  $result
     */
    public static function fromResult(array $result): self
    {
        return new self(
            answer: $result['answer'],
            user_message_id: $result['user_message_id'],
            assistant_message_id: $result['assistant_message_id'],
        );
    }
}

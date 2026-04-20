<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Data;

use Spatie\LaravelData\Data;
use Illuminate\Support\Carbon;

class AiChatMessageResource extends Data
{
    public function __construct(
        public string $id,
        public string $role, // 'user' | 'assistant'
        public ?string $search_mode,
        public string $content,
        public Carbon $created_at,
        public ?array $metadata,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Keyword\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Domain\Keyword\Enums\KeywordStatus;

class KeywordStatusController
{
    /**
     * Get all keyword statuses.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(KeywordStatus::toArray());
    }
}

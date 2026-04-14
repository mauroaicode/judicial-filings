<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Keyword\Services;

use Src\Application\AppUser\Keyword\Data\KeywordData;
use Src\Domain\Keyword\Models\Keyword;

class CreateKeywordService
{
    /**
     * Handle the keyword creation.
     */
    public function handle(KeywordData $data, string $organizationId): Keyword
    {
        /** @var Keyword $keyword */
        $keyword = Keyword::query()->create([
            'organization_id' => $organizationId,
            'name' => $data->name,
            'keyword' => $data->keyword,
            'status' => $data->status,
        ]);

        return $keyword;
    }
}

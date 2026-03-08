<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Keyword\Services;

use Src\Application\AppUser\Keyword\Data\KeywordData;
use Src\Domain\Keyword\Models\Keyword;

class UpdateKeywordService
{
    /**
     * Handle the keyword update.
     */
    public function handle(string $id, KeywordData $data, string $organizationId): Keyword
    {
        $keyword = Keyword::query()
            ->whereOrganization($organizationId)
            ->findOrFail($id);

        $keyword->update([
            'name' => $data->name,
            'keyword' => $data->keyword,
            'status' => $data->status,
        ]);

        return $keyword;
    }
}

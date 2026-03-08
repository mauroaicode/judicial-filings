<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Keyword\Services;

use Src\Domain\Keyword\Models\Keyword;

class DeleteKeywordService
{
    /**
     * Handle the keyword deletion.
     */
    public function handle(string $id, string $organizationId): void
    {
        $keyword = Keyword::query()
            ->whereOrganization($organizationId)
            ->findOrFail($id);

        $keyword->delete();
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\CourtDirectory\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;

readonly class ListCourtDirectorySyncRunsService
{
    /**
     * @return LengthAwarePaginator<int, CourtDirectorySyncRun>
     */
    public function handle(): LengthAwarePaginator
    {
        return CourtDirectorySyncRun::query()
            ->latest('started_at')
            ->paginate(20);
    }
}

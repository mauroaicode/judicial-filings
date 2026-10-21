<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class AppUserPresenceSummaryResult
{
    public function __construct(
        public LengthAwarePaginator $activities,
        public string $from,
        public string $to,
        public ?string $organizationId,
        public int $users,
        public int $loginCount,
        public int $daysOnline,
    ) {}
}

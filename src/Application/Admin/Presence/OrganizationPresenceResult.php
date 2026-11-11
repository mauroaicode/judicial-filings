<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class OrganizationPresenceResult
{
    public function __construct(
        public LengthAwarePaginator $organizations,
        public string $status,
        public int $inactiveDays,
        public ?bool $isActive,
        public ?string $search,
        public int $never,
        public int $stale,
        public int $recent,
    ) {}
}

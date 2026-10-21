<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Resources;

use Spatie\LaravelData\Resource;

class OnlineAppUsersResource extends Resource
{
    /**
     * @param  list<array<string, mixed>>  $users
     */
    public function __construct(
        public bool $presence_available,
        public int $total,
        public array $users,
    ) {}
}

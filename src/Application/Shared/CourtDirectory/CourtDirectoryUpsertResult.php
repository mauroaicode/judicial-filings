<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory;

readonly class CourtDirectoryUpsertResult
{
    public function __construct(
        public int $fetched,
        public int $upserted,
        public int $markedInactive,
    ) {}
}

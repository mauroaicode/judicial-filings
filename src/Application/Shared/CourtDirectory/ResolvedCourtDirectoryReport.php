<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory;

readonly class ResolvedCourtDirectoryReport
{
    public function __construct(
        public string $resourceKey,
        public string $apiBaseUrl,
    ) {}
}

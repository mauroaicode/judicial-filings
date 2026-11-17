<?php

declare(strict_types=1);

namespace Src\Domain\CourtDirectory;

readonly class CourtDirectoryRow
{
    public function __construct(
        public string $email,
        public string $name,
        public ?string $corporation,
        public ?string $specialty,
        public ?string $department,
        public ?string $city,
        public ?string $accountType,
        public ?string $deskCode,
    ) {}
}

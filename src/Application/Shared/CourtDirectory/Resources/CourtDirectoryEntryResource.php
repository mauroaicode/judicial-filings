<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;

class CourtDirectoryEntryResource extends Resource
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public ?string $corporation,
        public ?string $specialty,
        public ?string $department,
        public ?string $city,
        public ?string $account_type,
        public ?string $desk_code,
        public bool $is_active,
    ) {}

    public static function fromModel(CourtDirectoryEntry $entry): self
    {
        return new self(
            id: $entry->id,
            email: $entry->email,
            name: $entry->name,
            corporation: $entry->corporation,
            specialty: $entry->specialty,
            department: $entry->department,
            city: $entry->city,
            account_type: $entry->account_type,
            desk_code: $entry->desk_code,
            is_active: $entry->is_active,
        );
    }
}

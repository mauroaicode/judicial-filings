<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Process\Models\ProcessSubject;

class ProcessSubjectResource extends Resource
{
    public function __construct(
        public string $id,
        public int $subject_registration_id,
        public string $subject_type,
        public bool $is_cited,
        public ?string $identification,
        public string $name_or_business_name,
    ) {}

    public static function fromModel(ProcessSubject $subject): self
    {
        return new self(
            id: $subject->id,
            subject_registration_id: $subject->subject_registration_id,
            subject_type: $subject->subject_type,
            is_cited: $subject->is_cited,
            identification: $subject->identification,
            name_or_business_name: StrParseHelper::toTitleCase($subject->name_or_business_name) ?? '',
        );
    }
}

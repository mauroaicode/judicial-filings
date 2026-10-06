<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class OrganizationProcessExportData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[In(['active', 'inactive', 'suspended'])]
        public ?string $status = null,
        #[Date]
        public ?string $created_at_from = null,
        #[Date]
        public ?string $created_at_to = null,
        #[Date]
        public ?string $updated_at_from = null,
        #[Date]
        public ?string $updated_at_to = null,
        #[BooleanType]
        public bool $include_plaintiffs = true,
        #[BooleanType]
        public bool $include_defendants = true,
        #[BooleanType]
        public bool $include_other_subjects = true,
        #[BooleanType]
        public bool $include_actions = false,
        #[Date]
        public ?string $actions_from = null,
        #[Date]
        public ?string $actions_to = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toFilterArray(): array
    {
        return [
            'status' => $this->status,
            'created_at_from' => $this->created_at_from,
            'created_at_to' => $this->created_at_to,
            'updated_at_from' => $this->updated_at_from,
            'updated_at_to' => $this->updated_at_to,
            'include_plaintiffs' => $this->include_plaintiffs,
            'include_defendants' => $this->include_defendants,
            'include_other_subjects' => $this->include_other_subjects,
            'include_actions' => $this->include_actions,
            'actions_from' => $this->actions_from,
            'actions_to' => $this->actions_to,
        ];
    }

    public function needsSubjects(): bool
    {
        return $this->include_plaintiffs || $this->include_defendants || $this->include_other_subjects;
    }

    /**
     * Rebuild filters from a persisted export payload.
     * Named without a `from*` prefix so Spatie Data does not treat it as a creation magician.
     */
    public static function hydrate(array $filters): self
    {
        return new self(
            status: isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : null,
            created_at_from: isset($filters['created_at_from']) && is_string($filters['created_at_from']) ? $filters['created_at_from'] : null,
            created_at_to: isset($filters['created_at_to']) && is_string($filters['created_at_to']) ? $filters['created_at_to'] : null,
            updated_at_from: isset($filters['updated_at_from']) && is_string($filters['updated_at_from']) ? $filters['updated_at_from'] : null,
            updated_at_to: isset($filters['updated_at_to']) && is_string($filters['updated_at_to']) ? $filters['updated_at_to'] : null,
            include_plaintiffs: (bool) ($filters['include_plaintiffs'] ?? true),
            include_defendants: (bool) ($filters['include_defendants'] ?? true),
            include_other_subjects: (bool) ($filters['include_other_subjects'] ?? true),
            include_actions: (bool) ($filters['include_actions'] ?? false),
            actions_from: isset($filters['actions_from']) && is_string($filters['actions_from']) ? $filters['actions_from'] : null,
            actions_to: isset($filters['actions_to']) && is_string($filters['actions_to']) ? $filters['actions_to'] : null,
        );
    }
}

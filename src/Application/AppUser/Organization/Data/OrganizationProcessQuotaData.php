<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Data;

use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class OrganizationProcessQuotaData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public int $active_processes_count,
        /** Effective limit. null = unlimited. */
        public ?int $max_active_processes,
        /** null when unlimited. */
        public ?int $remaining_slots,
        public bool $is_unlimited,
        public bool $is_at_limit,
        public bool $can_add_process,
    ) {}

    /**
     * @param  array{
     *     active_processes_count: int,
     *     max_active_processes: int|null,
     *     remaining_slots: int|null,
     *     is_unlimited: bool,
     *     is_at_limit: bool,
     *     can_add_process: bool
     * }  $summary
     */
    public static function fromSummary(array $summary): self
    {
        return new self(
            active_processes_count: $summary['active_processes_count'],
            max_active_processes: $summary['max_active_processes'],
            remaining_slots: $summary['remaining_slots'],
            is_unlimited: $summary['is_unlimited'],
            is_at_limit: $summary['is_at_limit'],
            can_add_process: $summary['can_add_process'],
        );
    }

    public static function attributes(): array
    {
        return [
            'active_processes_count' => __('data.active_processes_count'),
            'max_active_processes' => __('data.max_active_processes'),
            'remaining_slots' => __('data.remaining_slots'),
            'is_unlimited' => __('data.is_unlimited'),
            'is_at_limit' => __('data.is_at_limit'),
            'can_add_process' => __('data.can_add_process'),
        ];
    }
}

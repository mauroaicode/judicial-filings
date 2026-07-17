<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<\Src\Domain\Process\Models\ProcessTimelineEvent>
 */
class ProcessTimelineEventQueryBuilder extends Builder
{
    public function visibleToOrganization(string $organizationId): self
    {
        return $this->where(function (Builder $query) use ($organizationId): void {
            $query->whereNull('organization_id')
                ->orWhere('organization_id', $organizationId);
        });
    }

    public function forProcess(string $processId): self
    {
        return $this->where('process_id', $processId);
    }

    public function latestFirst(): self
    {
        return $this->latest('occurred_at')
            ->latest('recorded_at')
            ->orderByDesc('id');
    }
}

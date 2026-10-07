<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Contracts\Database\Query\Builder as QueryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Src\Domain\Process\Models\ProcessExport;

/**
 * @extends Builder<ProcessExport>
 */
class ProcessExportQueryBuilder extends Builder
{
    /**
     * @return $this
     */
    public function withDetails(): self
    {
        return $this->with(['organization:id,name', 'requestedByUser:id,name,last_name,email']);
    }

    /**
     * @return $this
     */
    public function whereOrganization(string $organizationId): self
    {
        return $this->where('organization_id', $organizationId);
    }

    /**
     * @return $this
     */
    public function whereStatus(?string $status): self
    {
        if ($status === null || $status === '') {
            return $this;
        }

        return $this->where('status', $status);
    }

    /**
     * @return $this
     */
    public function whereOrganizationNameLike(?string $name): self
    {
        if ($name === null || trim($name) === '') {
            return $this;
        }

        $term = trim($name);

        $this->whereHas('organization', function (QueryContract $query) use ($term): void {
            $query->where('name', 'LIKE', '%'.$term.'%');
        });

        return $this;
    }

    /**
     * @return $this
     */
    public function whereCreatedAtBetween(?string $from, ?string $to): self
    {
        if ($from) {
            $this->where('created_at', '>=', Date::parse($from)->startOfDay());
        }

        if ($to) {
            $this->where('created_at', '<=', Date::parse($to)->endOfDay());
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function orderedByCreatedAtDesc(): self
    {
        $this->latest();

        return $this;
    }
}

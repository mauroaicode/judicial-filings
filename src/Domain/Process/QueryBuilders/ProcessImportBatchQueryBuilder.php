<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Contracts\Database\Query\Builder as QueryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Src\Domain\Process\Models\ProcessImportBatch;

/**
 * @extends Builder<ProcessImportBatch>
 */
class ProcessImportBatchQueryBuilder extends Builder
{
    /**
     * Filter batches that belong to a specific organization.
     *
     * @return $this
     */
    public function whereOrganization(string $organizationId): self
    {
        return $this->where('organization_id', $organizationId);
    }

    /**
     * Filter batches by status.
     *
     * @return $this
     */
    public function whereStatus(string $status): self
    {
        return $this->where('status', $status);
    }

    /**
     * Partial match on related organization name (admin filters).
     *
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
     * Partial match on import file name.
     *
     * @return $this
     */
    public function whereFileNameLike(?string $fileName): self
    {
        if ($fileName === null || trim($fileName) === '') {
            return $this;
        }

        $term = trim($fileName);
        $this->where('file_name', 'LIKE', '%'.$term.'%');

        return $this;
    }

    /**
     * Filter batches whose persisted errors JSON has at least one entry (MySQL JSON).
     *
     * @return $this
     */
    public function whereHasRecordedErrors(): self
    {
        $this->whereRaw('JSON_LENGTH(COALESCE(errors, ?)) > 0', [json_encode([])]);

        return $this;
    }

    /**
     * Filter batches with no error entries in JSON (empty array or null).
     *
     * @return $this
     */
    public function whereHasNoRecordedErrors(): self
    {
        $this->whereRaw('JSON_LENGTH(COALESCE(errors, ?)) = 0', [json_encode([])]);

        return $this;
    }

    /**
     * Filter by batch created_at date range (inclusive).
     *
     * @return $this
     */
    public function whereCreatedAtBetween(?string $from, ?string $to): self
    {
        if (! $from && ! $to) {
            return $this;
        }

        if ($from && $to) {
            $this->whereBetween('created_at', [
                Date::parse($from)->startOfDay(),
                Date::parse($to)->endOfDay(),
            ]);

            return $this;
        }

        if ($from) {
            $this->where('created_at', '>=', Date::parse($from)->startOfDay());

            return $this;
        }

        $this->where('created_at', '<=', Date::parse((string) $to)->endOfDay());

        return $this;
    }

    /**
     * Order batches by created_at (most recent first).
     *
     * @return $this
     */
    public function orderedByCreatedAt(): self
    {
        return $this->latest('created_at');
    }

    /**
     * Eager-load organization columns for admin listing (name display).
     *
     * @return $this
     */
    public function withOrganizationDetails(): self
    {
        $this->with(['organization:id,name']);

        return $this;
    }
}

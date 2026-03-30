<?php

declare(strict_types=1);

namespace Src\Domain\Keyword\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Domain\Keyword\Enums\KeywordStatus;

class KeywordQueryBuilder extends Builder
{
    /**
     * Filter by name or keyword.
     */
    public function whereSearch(string $search): self
    {
        return $this->where(function (Builder $query) use ($search): void {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('keyword', 'like', "%{$search}%");
        });
    }

    /**
     * Filter by name.
     */
    public function whereName(string $name): self
    {
        return $this->where('name', 'like', "%{$name}%");
    }

    /**
     * Filter by word/keyword.
     */
    public function whereKeyword(string $keyword): self
    {
        return $this->where('keyword', 'like', "%{$keyword}%");
    }

    /**
     * Filter by status.
     */
    public function whereStatus(KeywordStatus $status): self
    {
        return $this->where('status', $status->value);
    }

    /**
     * Filter by organization.
     */
    public function whereOrganization(string $organizationId): self
    {
        return $this->where('organization_id', $organizationId);
    }

    /**
     * Order by creation date.
     */
    public function orderByCreatedAt(string $direction = 'desc'): self
    {
        return $this->orderBy('created_at', $direction);
    }
}

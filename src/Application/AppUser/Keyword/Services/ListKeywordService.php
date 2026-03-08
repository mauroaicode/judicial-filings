<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Keyword\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Src\Application\AppUser\Keyword\Data\KeywordFilterData;
use Src\Application\AppUser\Keyword\Resources\KeywordResource;
use Src\Domain\Keyword\Enums\KeywordStatus;
use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Keyword\QueryBuilders\KeywordQueryBuilder;

class ListKeywordService
{
    /**
     * Handle the keyword listing.
     *
     * @return LengthAwarePaginator<\Src\Domain\Keyword\Models\Keyword>
     */
    public function handle(KeywordFilterData $filters, string $organizationId, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Keyword::query()->whereOrganization($organizationId);

        $this->applyFilters($query, $filters);

        $paginator = $query->orderByCreatedAt()->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(function (Keyword $keyword) {
            return KeywordResource::fromModel($keyword)->toArray();
        })->all();

        return new LengthAwarePaginatorImpl(
            $items,
            $paginator->total(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters(KeywordQueryBuilder $query, KeywordFilterData $filters): void
    {
        if ($filters->name) {
            $query->whereName($filters->name);
        }

        if ($filters->keyword) {
            $query->whereKeyword($filters->keyword);
        }

        if ($filters->status) {
            $query->whereStatus(KeywordStatus::from($filters->status));
        }
    }
}

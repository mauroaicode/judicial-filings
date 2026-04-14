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
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function handle(KeywordFilterData $filters, string $organizationId, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Keyword::query()->whereOrganization($organizationId);

        $this->applyFilters($query, $filters);

        /** @var LengthAwarePaginatorImpl<int, Keyword> $paginator */
        $paginator = $query->orderByCreatedAt()->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(fn (Keyword $keyword): array => KeywordResource::fromModel($keyword)->toArray())->all();

        /** @var LengthAwarePaginatorImpl<int, array<string, mixed>> $result */
        $result = new LengthAwarePaginatorImpl(
            $items,
            $paginator->total(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $result;
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

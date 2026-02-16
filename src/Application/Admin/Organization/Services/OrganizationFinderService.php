<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Src\Application\Admin\Organization\Data\OrganizationFilterData;
use Src\Application\Admin\Organization\Resources\OrganizationIndexResource;
use Src\Domain\Organization\Models\Organization;

readonly class OrganizationFinderService
{
    /**
     * Get paginated organizations with filters, ordered by created_at.
     *
     * @param  int  $perPage  Number of items per page.
     * @param  int  $page  Current page (1-based).
     */
    public function handle(OrganizationFilterData $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Organization::query()
            ->withRelations()
            ->filters($filters)
            ->orderedByCreatedAt();

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $startIndex = ($page - 1) * $perPage + 1;
        $items = $paginator->getCollection()->values()->map(fn (Organization $organization, int $position): array => OrganizationIndexResource::fromModel($organization, $startIndex + $position)->toArray())->all();

        $lengthAwarePaginator = new LengthAwarePaginatorImpl(
            $items,
            $paginator->total(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $lengthAwarePaginator->appends(request()->query());
    }
}

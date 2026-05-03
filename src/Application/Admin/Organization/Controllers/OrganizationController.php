<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Application\Admin\Organization\Data\OrganizationFilterData;
use Src\Application\Admin\Organization\Data\StoreOrganizationData;
use Src\Application\Admin\Organization\Resources\OrganizationResource;
use Src\Application\Admin\Organization\Resources\OrganizationStatsResource;
use Src\Application\Admin\Organization\Services\OrganizationCreatorService;
use Src\Application\Admin\Organization\Services\OrganizationFinderService;
use Src\Application\Admin\Organization\Services\OrganizationStatsService;
use Throwable;

readonly class OrganizationController
{
    /**
     * Display a paginated listing of organizations with optional filters.
     */
    public function index(Request $request, OrganizationFinderService $organizationFinderService): LengthAwarePaginator
    {
        $filters = OrganizationFilterData::from($request->query());
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        return $organizationFinderService->handle($filters, $perPage, $page);
    }

    /**
     * Aggregate counts for organizations (total, active/inactive, natural/juridical).
     */
    public function stats(OrganizationStatsService $organizationStatsService): OrganizationStatsResource
    {
        return $organizationStatsService->handle();
    }

    /**
     * Store a newly created organization and its first owner (AppUser).
     * Sends account-created email to the owner after commit.
     *
     * @throws Throwable
     */
    public function store(OrganizationCreatorService $organizationCreatorService, StoreOrganizationData $storeOrganizationData): Response
    {
        $organization = $organizationCreatorService->handle($storeOrganizationData);

        return response(OrganizationResource::fromModel($organization)->toArray(), 201);
    }
}

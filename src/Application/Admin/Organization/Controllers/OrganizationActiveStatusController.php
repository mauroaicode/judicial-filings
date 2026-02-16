<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Organization\Services\OrganizationActiveStatusListService;

readonly class OrganizationActiveStatusController
{
    /**
     * List organization active/inactive statuses for dropdowns/filters in frontend.
     */
    public function index(OrganizationActiveStatusListService $organizationActiveStatusListService): JsonResponse
    {
        return response()->json([
            'data' => $organizationActiveStatusListService->handle(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\JudicialSync\Data\AdminJudicialSyncData;
use Src\Application\Admin\JudicialSync\Data\AdminOrganizationJudicialSyncData;
use Src\Application\Admin\JudicialSync\Services\AdminJudicialSyncService;
use Src\Application\Admin\JudicialSync\Services\AdminOrganizationJudicialSyncService;
use Src\Domain\Organization\Models\Organization;

readonly class AdminJudicialSyncController
{
    public function __construct(
        private AdminJudicialSyncService $adminJudicialSyncService,
        private AdminOrganizationJudicialSyncService $adminOrganizationJudicialSyncService,
    ) {}

    public function sync(AdminJudicialSyncData $data): JsonResponse
    {
        $resource = $this->adminJudicialSyncService->handle($data);

        return response()->json($resource->toArray(), 200);
    }

    public function syncOrganization(Organization $organization, AdminOrganizationJudicialSyncData $data): JsonResponse
    {
        $resource = $this->adminOrganizationJudicialSyncService->handle($organization, $data);

        return response()->json($resource->toArray(), 200);
    }
}

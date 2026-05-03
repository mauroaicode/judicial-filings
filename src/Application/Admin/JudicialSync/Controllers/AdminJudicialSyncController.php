<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\JudicialSync\Data\AdminJudicialSyncData;
use Src\Application\Admin\JudicialSync\Services\AdminJudicialSyncService;

readonly class AdminJudicialSyncController
{
    public function __construct(
        private AdminJudicialSyncService $adminJudicialSyncService,
    ) {}

    public function sync(AdminJudicialSyncData $data): JsonResponse
    {
        $resource = $this->adminJudicialSyncService->handle($data);

        return response()->json($resource->toArray(), 200);
    }
}

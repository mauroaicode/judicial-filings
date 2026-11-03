<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\JudicialSync\Services\AdminJudicialSyncLiveStatusService;

readonly class AdminJudicialSyncLiveStatusController
{
    public function __construct(
        private AdminJudicialSyncLiveStatusService $adminJudicialSyncLiveStatusService,
    ) {}

    /**
     * Live sync status for the admin header (bootstrap + reconnect).
     * Subscribe to private channel `admin.judicial-sync` for realtime updates.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json($this->adminJudicialSyncLiveStatusService->handle());
    }
}

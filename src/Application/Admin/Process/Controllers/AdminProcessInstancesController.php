<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Process\Services\AdminProcessInstancesService;

readonly class AdminProcessInstancesController
{
    public function __construct(
        private AdminProcessInstancesService $adminProcessInstancesService
    ) {}

    /**
     * Return all instances (same process_number) across the system (admin view),
     * so the frontend can render a switcher in the process detail view.
     */
    public function index(string $processId): JsonResponse
    {
        $instances = $this->adminProcessInstancesService->handle($processId);

        if ($instances->isEmpty()) {
            abort(404, __('process.not_found'));
        }

        return response()->json($instances->all());
    }
}

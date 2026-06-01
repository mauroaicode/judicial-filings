<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Shared\Process\Data\ToggleProcessStatusData;
use Src\Application\Shared\Process\Services\ToggleProcessStatusService;

readonly class AdminProcessStatusController
{
    public function __construct(
        private ToggleProcessStatusService $toggleProcessStatusService,
    ) {}

    /**
     * Activate or deactivate a process for a specific organization (admin).
     */
    public function update(
        string $processId,
        string $organizationId,
        ToggleProcessStatusData $data,
    ): JsonResponse {
        $this->toggleProcessStatusService->handle($processId, $organizationId, $data->is_active);

        $message = $data->is_active
            ? __('process.activated_successfully')
            : __('process.deactivated_successfully');

        return response()->json(['message' => $message]);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Shared\Process\Data\UpdateProcessConfigData;
use Src\Application\Shared\Process\Services\UpdateProcessConfigService;
use Src\Domain\Process\Enums\ProcessLawyerRole;

readonly class AdminProcessConfigController
{
    public function __construct(
        private UpdateProcessConfigService $updateProcessConfigService,
    ) {}

    /**
     * Returns available lawyer roles for process configuration (admin).
     */
    public function roles(): JsonResponse
    {
        $roles = collect(ProcessLawyerRole::cases())->map(fn (ProcessLawyerRole $role): array => [
            'value' => $role->value,
            'label' => $role->getLabel(),
        ]);

        return response()->json($roles);
    }

    /**
     * Assign lawyer role for a process within a specific organization (admin).
     */
    public function update(
        string $processId,
        string $organizationId,
        UpdateProcessConfigData $data,
    ): JsonResponse {
        $this->updateProcessConfigService->handle($organizationId, $processId, $data);

        return response()->json([
            'message' => __('process.config_updated_successfully'),
        ]);
    }
}

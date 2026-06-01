<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Process\Data\BulkUpdateProcessConfigData;
use Src\Application\AppUser\Process\Services\BulkUpdateProcessConfigService;
use Src\Application\Shared\Process\Data\UpdateProcessConfigData;
use Src\Application\Shared\Process\Services\UpdateProcessConfigService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Enums\ProcessLawyerRole;

readonly class ProcessConfigController
{
    public function __construct(
        private UpdateProcessConfigService $updateProcessConfigService,
        private BulkUpdateProcessConfigService $bulkUpdateProcessConfigService
    ) {}

    /**
     * Update configuration for multiple processes.
     */
    public function bulkUpdate(BulkUpdateProcessConfigData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $summary = $this->bulkUpdateProcessConfigService->handle($organization->id, $data);

        return response()->json($summary);
    }

    /**
     * Update the role and alert level for a given process/organization.
     *
     * @param  string  $id  The process ID (UUID).
     * @param  UpdateProcessConfigData  $data  Validation DTO.
     */
    public function update(string $id, UpdateProcessConfigData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $this->updateProcessConfigService->handle($organization->id, $id, $data);

        return response()->json([
            'message' => __('process.config_updated_successfully'),
        ]);
    }

    /**
     * Returns a list of available lawyer roles with their localized labels.
     */
    public function roles(): JsonResponse
    {
        $roles = collect(ProcessLawyerRole::cases())->map(fn (ProcessLawyerRole $role): array => [
            'value' => $role->value,
            'label' => $role->getLabel(),
        ]);

        return response()->json($roles);
    }
}

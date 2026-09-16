<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Process\Data\AttachAdminProcessOrganizationsData;
use Src\Application\Admin\Process\Resources\AdminProcessOrganizationResource;
use Src\Application\Admin\Process\Services\AttachAdminProcessOrganizationsService;
use Src\Application\Shared\Process\Services\TrashOrganizationProcessesService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

readonly class AdminProcessOrganizationController
{
    public function __construct(
        private AttachAdminProcessOrganizationsService $attachAdminProcessOrganizationsService,
        private TrashOrganizationProcessesService $trashOrganizationProcessesService,
    ) {}

    /**
     * Attach one or more interested organizations to a process (admin).
     * Already linked organizations are kept. Previously trashed links are restored.
     */
    public function store(string $processId, AttachAdminProcessOrganizationsData $data): JsonResponse
    {
        $process = $this->attachAdminProcessOrganizationsService->handle(
            $processId,
            $data,
            auth()->id(),
        );

        return response()->json([
            'message' => __('process.organizations_attached_successfully'),
            'organizations' => $this->organizationsPayload($process),
        ]);
    }

    /**
     * Remove an interested organization from a process (soft-delete the tracking link).
     */
    public function destroy(string $processId, string $organizationId): JsonResponse
    {
        $process = Process::query()->find($processId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $result = $this->trashOrganizationProcessesService->handle(
            $organizationId,
            [$processId],
            auth()->id(),
        );

        if ($result['trashed_count'] === 0) {
            abort(404, __('process.relationship_not_found'));
        }

        $process = $process->refresh()->load('organizations');

        return response()->json([
            'message' => __('process.moved_to_trash'),
            'organizations' => $this->organizationsPayload($process),
        ]);
    }

    /**
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    private function organizationsPayload(Process $process): array
    {
        $items = $process->organizations
            ->sortByDesc(fn (Organization $org) => $org->pivot?->interest_date)
            ->values()
            ->map(fn (Organization $org): array => AdminProcessOrganizationResource::fromOrganizationAndProcess($org, $process)->toArray())
            ->all();

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }
}

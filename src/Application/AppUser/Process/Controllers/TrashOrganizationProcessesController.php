<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Organization\Services\ResolveUserOrganizationService;
use Src\Application\AppUser\Process\Data\TrashOrganizationProcessesData;
use Src\Application\AppUser\Process\Services\TrashAppUserOrganizationProcessesService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

readonly class TrashOrganizationProcessesController
{
    public function __construct(
        private ResolveUserOrganizationService $resolveUserOrganizationService,
        private TrashAppUserOrganizationProcessesService $trashAppUserOrganizationProcessesService,
    ) {}

    /**
     * Soft-trash one or more (or all) processes for the authenticated lawyer's organization.
     * Frees cupos automatically because quota counts only active non-trashed links.
     */
    public function destroyMany(TrashOrganizationProcessesData $data): JsonResponse
    {
        $organization = $this->resolveOrganization();

        $result = $this->trashAppUserOrganizationProcessesService->handle(
            $organization->id,
            $data->process_ids,
            $data->all,
            auth()->id(),
        );

        if ($result['trashed_count'] === 0) {
            abort(422, __('process.trash_nothing_trashed'));
        }

        return response()->json([
            'message' => __('process.moved_to_trash'),
            'trashed_count' => $result['trashed_count'],
            'trashed_ids' => $result['trashed_ids'],
            'skipped' => $result['skipped'],
            'quota' => $result['quota'],
        ]);
    }

    /**
     * Soft-trash a single process (and sibling instances of the same radicado) for the org.
     */
    public function destroy(string $id): JsonResponse
    {
        $organization = $this->resolveOrganization();

        $result = $this->trashAppUserOrganizationProcessesService->handle(
            $organization->id,
            [$id],
            all: false,
            deletedBy: auth()->id(),
        );

        if ($result['trashed_count'] === 0) {
            abort(404, __('process.relationship_not_found'));
        }

        return response()->json([
            'message' => __('process.moved_to_trash'),
            'trashed_count' => $result['trashed_count'],
            'trashed_ids' => $result['trashed_ids'],
            'quota' => $result['quota'],
        ]);
    }

    private function resolveOrganization(): Organization
    {
        $organization = $this->resolveUserOrganizationService->handle();

        if ($organization instanceof Organization) {
            return $organization;
        }

        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $fallback = $appUser->organizations()->first();

        if (! $fallback) {
            abort(422, __('process.user_has_no_organization'));
        }

        return $fallback;
    }
}

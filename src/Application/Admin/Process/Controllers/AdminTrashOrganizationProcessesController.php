<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Process\Data\TrashOrganizationProcessData;
use Src\Application\Admin\Process\Data\TrashOrganizationProcessesData;
use Src\Application\Admin\Process\Services\TrashOrganizationProcessesService;

readonly class AdminTrashOrganizationProcessesController
{
    public function __construct(
        private TrashOrganizationProcessesService $trashOrganizationProcessesService,
    ) {}

    /**
     * Move one or more organization↔process links to trash (checkbox bulk).
     */
    public function destroyMany(TrashOrganizationProcessesData $data): JsonResponse
    {
        $result = $this->trashOrganizationProcessesService->handle(
            $data->organization_id,
            $data->process_ids,
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
        ]);
    }

    /**
     * Move a single organization↔process link to trash (process detail).
     */
    public function destroy(string $id, TrashOrganizationProcessData $data): JsonResponse
    {
        $result = $this->trashOrganizationProcessesService->handle(
            $data->organization_id,
            [$id],
            auth()->id(),
        );

        if ($result['trashed_count'] === 0) {
            abort(404, __('process.relationship_not_found'));
        }

        return response()->json([
            'message' => __('process.moved_to_trash'),
            'trashed_count' => $result['trashed_count'],
            'trashed_ids' => $result['trashed_ids'],
        ]);
    }
}

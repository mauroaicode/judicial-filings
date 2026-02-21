<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\Admin\Process\Services\ProcessImportService;
use Src\Domain\Process\Models\ProcessImportBatch;

readonly class ProcessImportController
{
    public function __construct(
        private ProcessImportService $processImportService,
    ) {}

    /**
     * @throws \Throwable
     */
    public function import(Request $request): JsonResponse
    {
        $result = $this->processImportService->handle($request);

        return response()->json($result['body'], $result['status']);
    }


    public function showBatch(string $id): JsonResponse
    {
        $batch = ProcessImportBatch::query()
            ->with('organization:id,name')
            ->find($id);

        if (! $batch) {
            return response()->json(['message' => __('process.import_batch_not_found')], 404);
        }

        return response()->json([
            'id' => $batch->id,
            'organization_id' => $batch->organization_id,
            'organization_name' => $batch->organization?->name,
            'file_name' => $batch->file_name,
            'status' => $batch->status,
            'total_count' => $batch->total_count,
            'enqueued_process_numbers' => $batch->enqueued_process_numbers ?? [],
            'success_count' => $batch->success_count,
            'failed_count' => $batch->failed_count,
            'errors' => $batch->errors,
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'created_at' => $batch->created_at->toIso8601String(),
        ]);
    }
}

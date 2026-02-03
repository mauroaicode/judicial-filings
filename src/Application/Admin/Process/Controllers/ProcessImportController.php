<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Process\Data\ProcessImportData;
use Src\Application\Admin\Process\Services\ProcessImportService;

readonly class ProcessImportController
{
    public function __construct(
        private ProcessImportService $processImportService
    ) {}

    /**
     * Import processes from Excel file (synchronous, no queue).
     */
    public function import(ProcessImportData $data): JsonResponse
    {
        $result = $this->processImportService->handle($data->file);

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Process\Data\ProcessImportFromExcelData;
use Src\Application\Admin\Process\Services\ProcessImportService;
use Throwable;

readonly class ProcessImportController
{
    public function __construct(
        private ProcessImportService $processImportService,
    ) {}

    /**
     * @throws Throwable
     */
    public function import(ProcessImportFromExcelData $data): JsonResponse
    {
        $result = $this->processImportService->handle(
            organizationId: $data->organization_id,
            file: $data->file,
            source: $data->source->value,
        );

        return response()->json($result['body'], $result['status']);
    }
}

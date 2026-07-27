<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Process\Data\ProcessActuacionesImportFromExcelData;
use Src\Application\Admin\Process\Services\ProcessActuacionesExcelImportService;
use Throwable;

readonly class ProcessActuacionesImportController
{
    public function __construct(
        private ProcessActuacionesExcelImportService $importService,
    ) {}

    /**
     * @throws Throwable
     */
    public function import(ProcessActuacionesImportFromExcelData $data): JsonResponse
    {
        $result = $this->importService->handle($data->file, auth()->id());

        return response()->json($result['body'], $result['status']);
    }
}

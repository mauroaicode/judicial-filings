<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\Process\Data\PrivateProcessExcelImportFromExcelData;
use Src\Application\Admin\Process\Services\PrivateProcessExcelImportService;
use Throwable;

readonly class PrivateProcessExcelImportController
{
    public function __construct(
        private PrivateProcessExcelImportService $privateProcessExcelImportService,
    ) {}

    /**
     * @throws Throwable
     */
    public function import(PrivateProcessExcelImportFromExcelData $data): JsonResponse
    {
        $result = $this->privateProcessExcelImportService->handle(
            $data->organization_id,
            $data->file,
            $data->data_source_slug,
            auth()->id(),
        );

        return response()->json($result['body'], $result['status']);
    }
}

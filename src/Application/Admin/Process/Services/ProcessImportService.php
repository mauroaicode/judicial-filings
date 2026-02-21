<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Throwable;
use Illuminate\Http\UploadedFile;


readonly class ProcessImportService
{
    public function __construct(
        private ProcessImportDataService  $dataService,
        private ProcessImportBatchService $batchService,
    )
    {
    }

    /**
     * Handles the full import flow: validates organization, processes data, queues batch if needed.
     *
     * @param string $organizationId Organization identifier
     * @param UploadedFile $file Uploaded import file
     * @return array{status: int, body: array<string, mixed>}
     * @throws Throwable
     */
    public function handle(string $organizationId, UploadedFile $file): array
    {
        $dataResult = $this->dataService->handle(
            organizationId: $organizationId,
            file: $file,
            fileName: $file->getClientOriginalName(),
            requestedById: auth()->id(),
        );

        if (!$dataResult->isReadyToEnqueue()) {
            return [
                'status' => $dataResult->status,
                'body' => $dataResult->body,
            ];
        }

        $body = $this->batchService->dispatch($dataResult);

        return [
            'status' => 202,
            'body' => $body,
        ];
    }
}

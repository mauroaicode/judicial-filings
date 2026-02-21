<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Http\Request;

readonly class ProcessImportService
{
    public function __construct(
        private ProcessImportDataService $dataService,
        private ProcessImportBatchService $batchService,
    ) {}

    /**
     * Flujo completo: validar y preparar datos, y si aplica encolar batch.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(Request $request): array
    {
        $dataResult = $this->dataService->handle($request);

        if (! $dataResult->isReadyToEnqueue()) {
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

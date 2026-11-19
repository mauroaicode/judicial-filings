<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ExportProcessPdfService
{
    public function __construct(
        private readonly AssembleProcessPdfViewDataService $assembleProcessPdfViewDataService,
    ) {}

    public function handle(string $processId, string $organizationId): Response
    {
        $viewData = $this->assembleProcessPdfViewDataService->handle($processId, $organizationId);

        return Pdf::loadView('pdfs.process-detail', $viewData)
            ->setPaper('letter', 'landscape')
            ->download((string) $viewData['filename']);
    }
}

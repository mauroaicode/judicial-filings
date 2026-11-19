<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Excel;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Src\Application\AppUser\Process\Services\ProcessDetailService;
use Src\Application\Shared\Helpers\ProcessNumberFormatHelper;
use Src\Application\Shared\Process\Data\ProcessActionFilterData;
use Src\Domain\Process\Models\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportProcessActionsExcelService
{
    public function __construct(
        private readonly ProcessDetailService $processDetailService,
        private readonly CollectProcessActionsExcelRowsService $rowsService,
    ) {}

    public function handle(
        string $processId,
        string $organizationId,
        ProcessActionFilterData $filters,
    ): BinaryFileResponse {
        $process = $this->processDetailService->handle($processId, $organizationId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $export = new ProcessActionsExcelExport($process, $filters, $this->rowsService);

        return Excel::download($export, $this->filename($process));
    }

    private function filename(Process $process): string
    {
        $formatted = ProcessNumberFormatHelper::display(
            $process->process_number,
            $process->process_number,
        );

        return 'actuaciones-'.(Str::slug($formatted) ?: 'proceso').'.xlsx';
    }
}

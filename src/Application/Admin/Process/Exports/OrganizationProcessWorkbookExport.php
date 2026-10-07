<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Application\Admin\Process\Support\OrganizationProcessExportActionQueryService;
use Src\Application\Admin\Process\Support\OrganizationProcessExportQueryService;

/**
 * Workbook orchestrator: processes sheet always; actuaciones sheet only when requested.
 */
class OrganizationProcessWorkbookExport implements WithMultipleSheets
{
    /** @var list<object>|null */
    private ?array $resolvedSheets = null;

    private ?OrganizationProcessesExcelExport $processesSheet = null;

    private ?OrganizationProcessActionsExcelExport $actionsSheet = null;

    public function __construct(
        private readonly string $organizationId,
        private readonly OrganizationProcessExportData $filters,
        private readonly OrganizationProcessExportQueryService $processQueryService,
        private readonly OrganizationProcessExportActionQueryService $actionQueryService,
    ) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        if ($this->resolvedSheets !== null) {
            return $this->resolvedSheets;
        }

        $this->processesSheet = new OrganizationProcessesExcelExport(
            organizationId: $this->organizationId,
            filters: $this->filters,
            queryService: $this->processQueryService,
        );

        $sheets = [$this->processesSheet];

        if ($this->filters->include_actions) {
            $this->actionsSheet = new OrganizationProcessActionsExcelExport(
                organizationId: $this->organizationId,
                filters: $this->filters,
                actionQueryService: $this->actionQueryService,
            );
            $sheets[] = $this->actionsSheet;
        }

        return $this->resolvedSheets = $sheets;
    }

    public function processRowCount(): int
    {
        return $this->processesSheet?->rowCount() ?? 0;
    }

    public function actionRowCount(): int
    {
        return $this->actionsSheet?->rowCount() ?? 0;
    }
}

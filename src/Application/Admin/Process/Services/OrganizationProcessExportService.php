<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Src\Application\Admin\Process\Data\AdminProcessExportHistoryFilterData;
use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Application\Admin\Process\Jobs\GenerateOrganizationProcessExportJob;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\Models\ProcessExport;
use Src\Domain\User\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class OrganizationProcessExportService
{
    public function __construct(
        private AdminListProcessExportHistoryService $listProcessExportHistoryService,
    ) {}

    public function queue(
        Organization $organization,
        OrganizationProcessExportData $filters,
        ?User $requestedBy = null,
    ): ProcessExport {
        $export = ProcessExport::query()->create([
            'organization_id' => $organization->id,
            'requested_by' => $requestedBy?->id,
            'status' => ProcessExportStatus::Pending,
            'filters' => $filters->toFilterArray(),
            'row_count' => 0,
            'action_row_count' => 0,
        ]);

        dispatch(new GenerateOrganizationProcessExportJob($export->id));

        return $export->load(['organization', 'requestedByUser']);
    }

    /**
     * Re-queue an export using the filters stored on a previous history row.
     */
    public function rerun(
        Organization $organization,
        string $sourceExportId,
        ?User $requestedBy = null,
    ): ProcessExport {
        $source = $this->findForOrganization($organization, $sourceExportId);
        $filters = OrganizationProcessExportData::hydrate($source->filters ?? []);

        return $this->queue($organization, $filters, $requestedBy);
    }

    /**
     * @return LengthAwarePaginator<int, ProcessExport>
     */
    public function historyForOrganization(
        Organization $organization,
        AdminProcessExportHistoryFilterData $filters,
    ): LengthAwarePaginator {
        return $this->listProcessExportHistoryService->handle(
            AdminProcessExportHistoryFilterData::from([
                'organization_id' => $organization->id,
                'status' => $filters->status,
                'created_at_from' => $filters->created_at_from,
                'created_at_to' => $filters->created_at_to,
                'per_page' => $filters->per_page,
            ])
        );
    }

    public function findForOrganization(Organization $organization, string $exportId): ProcessExport
    {
        $export = ProcessExport::query()
            ->withDetails()
            ->where('organization_id', $organization->id)
            ->whereKey($exportId)
            ->first();

        if (! $export instanceof ProcessExport) {
            throw new NotFoundHttpException('Export not found.');
        }

        return $export;
    }

    public function download(ProcessExport $export): StreamedResponse
    {
        if (! $export->isDownloadable()) {
            throw new NotFoundHttpException('Export file is not available.');
        }

        $disk = $export->disk ?: (string) config('process-export.disk', 'local');
        $path = (string) $export->file_path;

        if (! Storage::disk($disk)->exists($path)) {
            throw new NotFoundHttpException('Export file is missing.');
        }

        return Storage::disk($disk)->download(
            $path,
            $export->file_name ?: basename($path),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Process\Data\AdminProcessExportHistoryFilterData;
use Src\Application\Admin\Process\Data\OrganizationProcessExportData;
use Src\Application\Admin\Process\Resources\ProcessExportResource;
use Src\Application\Admin\Process\Services\OrganizationProcessExportService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessExport;
use Src\Domain\User\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class OrganizationProcessExportController
{
    public function __construct(
        private OrganizationProcessExportService $exportService,
    ) {}

    public function store(
        Organization $organization,
        OrganizationProcessExportData $data,
        Request $request,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        $export = $this->exportService->queue($organization, $data, $user);

        return response()->json([
            'message' => 'Export queued. You will receive an in-app notification when it finishes.',
            'data' => ProcessExportResource::fromModel($export),
        ], 202);
    }

    /**
     * Organization-scoped export history (includes saved filters for re-run).
     */
    public function index(
        Organization $organization,
        AdminProcessExportHistoryFilterData $filters,
    ): LengthAwarePaginator {
        /** @var LengthAwarePaginator<int, ProcessExport> $paginator */
        $paginator = $this->exportService->historyForOrganization($organization, $filters);

        $paginator->through(
            fn (ProcessExport $export): ProcessExportResource => ProcessExportResource::fromModel($export)
        );

        return $paginator;
    }

    public function show(
        Organization $organization,
        string $processExport,
    ): JsonResponse {
        $export = $this->exportService->findForOrganization($organization, $processExport);

        return response()->json([
            'data' => ProcessExportResource::fromModel($export),
        ]);
    }

    /**
     * Re-export using the filters stored on a previous export history row.
     */
    public function rerun(
        Organization $organization,
        string $processExport,
        Request $request,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        $export = $this->exportService->rerun($organization, $processExport, $user);

        return response()->json([
            'message' => 'Export re-queued with the same filters. You will receive an in-app notification when it finishes.',
            'data' => ProcessExportResource::fromModel($export),
        ], 202);
    }

    public function download(
        Organization $organization,
        string $processExport,
    ): StreamedResponse {
        $export = $this->exportService->findForOrganization($organization, $processExport);

        return $this->exportService->download($export);
    }
}

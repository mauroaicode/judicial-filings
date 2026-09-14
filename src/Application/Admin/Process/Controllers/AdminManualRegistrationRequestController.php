<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Process\Data\AdminManualRegistrationRequestFilterData;
use Src\Application\Admin\Process\Data\UpdateManualRegistrationRequestStatusData;
use Src\Application\Admin\Process\Resources\AdminManualRegistrationRequestResource;
use Src\Application\Admin\Process\Services\AdminListManualRegistrationRequestsService;
use Src\Application\Admin\Process\Services\UpdateManualRegistrationRequestStatusService;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;

readonly class AdminManualRegistrationRequestController
{
    public function __construct(
        private AdminListManualRegistrationRequestsService $listService,
        private UpdateManualRegistrationRequestStatusService $updateStatusService,
    ) {}

    /**
     * Paginated ops queue of alta-manual requests (default: pending).
     *
     * Query: status, reason, organization, process_number, per_page.
     */
    public function index(AdminManualRegistrationRequestFilterData $filters): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, ManualRegistrationRequest> $paginator */
        $paginator = $this->listService->handle($filters);

        $paginator->through(
            fn (ManualRegistrationRequest $request): AdminManualRegistrationRequestResource => AdminManualRegistrationRequestResource::fromModel($request)
        );

        return $paginator;
    }

    /**
     * Mark a pending request as registered or rejected after private-import / ops decision.
     */
    public function updateStatus(UpdateManualRegistrationRequestStatusData $data, string $id): JsonResponse
    {
        $status = ManualRegistrationRequestStatus::from($data->status);
        $request = $this->updateStatusService->handle($id, $status);

        return response()->json([
            'message' => __('process.manual_registration_status_updated'),
            'data' => AdminManualRegistrationRequestResource::fromModel($request)->toArray(),
        ]);
    }
}

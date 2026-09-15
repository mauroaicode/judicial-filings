<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Process\Data\StoreManualRegistrationRequestData;
use Src\Application\AppUser\Process\Resources\ManualRegistrationRequestResource;
use Src\Application\AppUser\Process\Services\ListPendingManualRegistrationRequestsService;
use Src\Application\AppUser\Process\Services\RequestManualProcessRegistrationService;
use Src\Domain\AppUser\Models\AppUser;

readonly class ManualRegistrationRequestController
{
    public function __construct(
        private ListPendingManualRegistrationRequestsService $listPendingManualRegistrationRequestsService,
        private RequestManualProcessRegistrationService $requestManualProcessRegistrationService,
    ) {}

    /**
     * Pending alta-manual requests for the authenticated user's organization (modal list).
     */
    public function index(): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $payload = $this->listPendingManualRegistrationRequestsService->handle($organization->id);

        return response()->json($payload);
    }

    /**
     * Lawyer confirms manual review after filling subjects / process class in the modal.
     * This is when Discord + admin WebSocket fire.
     */
    public function store(StoreManualRegistrationRequestData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $request = $this->requestManualProcessRegistrationService->handle(
            $data->process_number,
            $organization->id,
            $appUser->id,
            $data->reason,
            $data->lawyer_role,
            [
                'process_class' => $data->process_class,
                'plaintiffs' => $data->normalizedPlaintiffs(),
                'defendants' => $data->normalizedDefendants(),
                'other_subjects' => $data->normalizedOtherSubjects(),
            ],
        );

        return response()->json([
            'message' => __('process.manual_registration_requested'),
            'status' => 'manual_review',
            'reason' => $request->reason->value,
            'reason_label' => $request->reason->label(),
            'request_id' => $request->id,
            'unassigned_actions_count' => $request->unassigned_actions_count,
            'data' => ManualRegistrationRequestResource::fromModel($request)->toArray(),
        ], 202);
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Process\Services\ListPendingManualRegistrationRequestsService;
use Src\Domain\AppUser\Models\AppUser;

readonly class ManualRegistrationRequestController
{
    public function __construct(
        private ListPendingManualRegistrationRequestsService $listPendingManualRegistrationRequestsService,
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
}

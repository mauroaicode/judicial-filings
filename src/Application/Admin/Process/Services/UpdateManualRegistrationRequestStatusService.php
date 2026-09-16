<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;

/**
 * Marks a manual registration request as rejected, or registers it by inserting the process.
 */
readonly class UpdateManualRegistrationRequestStatusService
{
    public function __construct(
        private RegisterManualProcessFromRequestService $registerManualProcessFromRequestService,
    ) {}

    public function handle(string $id, ManualRegistrationRequestStatus $status): ManualRegistrationRequest
    {
        if ($status === ManualRegistrationRequestStatus::Pending) {
            abort(422, __('process.manual_registration_invalid_status_transition'));
        }

        $request = ManualRegistrationRequest::query()->find($id);

        if (! $request instanceof ManualRegistrationRequest) {
            abort(404, __('process.manual_registration_request_not_found'));
        }

        if ($request->status !== ManualRegistrationRequestStatus::Pending) {
            abort(422, __('process.manual_registration_already_resolved'));
        }

        if ($status === ManualRegistrationRequestStatus::Registered) {
            return $this->registerManualProcessFromRequestService->handle($request)['request'];
        }

        $request->update([
            'status' => $status,
            'resolved_at' => now(),
        ]);

        return $request->fresh(['appUser', 'organization']) ?? $request;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Application\Shared\Services\Notification\NotifyAppUserManualRegistrationCompletedService;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;

/**
 * Marks a manual registration request as registered or rejected after ops completes (or declines) digitación.
 */
readonly class UpdateManualRegistrationRequestStatusService
{
    public function __construct(
        private NotifyAppUserManualRegistrationCompletedService $notifyAppUserCompletedService,
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

        $request->update([
            'status' => $status,
            'resolved_at' => now(),
        ]);

        $request = $request->fresh(['appUser', 'organization']) ?? $request;

        if ($status === ManualRegistrationRequestStatus::Registered) {
            $this->notifyAppUserCompletedService->handle($request);
        }

        return $request;
    }
}

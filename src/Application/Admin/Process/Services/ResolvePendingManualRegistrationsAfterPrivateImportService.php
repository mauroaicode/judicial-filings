<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Application\Shared\Services\Notification\NotifyAppUserManualRegistrationCompletedService;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;

/**
 * After private Excel import, mark matching pending alta-manual requests as registered and notify lawyers.
 */
readonly class ResolvePendingManualRegistrationsAfterPrivateImportService
{
    public function __construct(
        private NotifyAppUserManualRegistrationCompletedService $notifyAppUserCompletedService,
    ) {}

    /**
     * @param  list<string>  $processNumbers
     * @return int Number of requests resolved
     */
    public function handle(string $organizationId, array $processNumbers): int
    {
        $numbers = array_values(array_unique(array_filter(
            array_map(static fn (string $n): string => preg_replace('/\D+/', '', $n) ?? '', $processNumbers),
            static fn (string $n): bool => strlen($n) === 23,
        )));

        if ($numbers === []) {
            return 0;
        }

        $pending = ManualRegistrationRequest::query()
            ->where('organization_id', $organizationId)
            ->where('status', ManualRegistrationRequestStatus::Pending->value)
            ->whereIn('process_number', $numbers)
            ->with(['appUser', 'organization'])
            ->get();

        $resolved = 0;

        foreach ($pending as $request) {
            $request->update([
                'status' => ManualRegistrationRequestStatus::Registered,
                'resolved_at' => now(),
            ]);

            $fresh = $request->fresh(['appUser', 'organization']) ?? $request;
            $this->notifyAppUserCompletedService->handle($fresh);
            $resolved++;
        }

        return $resolved;
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Support\Collection;
use Src\Application\AppUser\Process\Resources\ManualRegistrationRequestResource;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Models\ManualRegistrationRequest;

/**
 * Lists pending manual registration requests for an organization (ops/advisor queue).
 */
readonly class ListPendingManualRegistrationRequestsService
{
    /**
     * @return array{count: int, data: list<array<string, mixed>>}
     */
    public function handle(string $organizationId): array
    {
        /** @var Collection<int, ManualRegistrationRequest> $requests */
        $requests = ManualRegistrationRequest::query()
            ->where('organization_id', $organizationId)
            ->where('status', ManualRegistrationRequestStatus::Pending->value)
            ->with('appUser')
            ->latest()
            ->get();

        return [
            'count' => $requests->count(),
            'data' => $requests
                ->map(fn (ManualRegistrationRequest $request): array => ManualRegistrationRequestResource::fromModel($request)->toArray())
                ->values()
                ->all(),
        ];
    }
}

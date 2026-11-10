<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Src\Domain\Organization\Models\Organization;

/**
 * Sends the consolidado of a single organization after its scoped sync batch finishes.
 * Sibling organizations keep their pending rows until their own digest runs.
 */
readonly class DispatchOrganizationSyncDigest
{
    public function handle(string $organizationId): void
    {
        if (! config('judicial-sync.auto_digest_after_sync', true)) {
            return;
        }

        $organization = Organization::query()->find($organizationId);

        if (! $organization instanceof Organization) {
            return;
        }

        dispatch_sync(new \Src\Application\Shared\Jobs\SendOrganizationDigestJob($organization));
    }
}

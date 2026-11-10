<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Src\Application\Shared\Jobs\SendOrganizationDigestJob;
use Src\Application\Shared\Services\Notification\DispatchOrganizationSyncDigest;
use Src\Domain\Organization\Models\Organization;

it('dispatches the digest only for the organization that finished its sync', function (): void {
    Bus::fake();

    $organization = Organization::factory()->create();

    app(DispatchOrganizationSyncDigest::class)->handle($organization->id);

    Bus::assertDispatchedSync(SendOrganizationDigestJob::class, function (SendOrganizationDigestJob $job) use ($organization): bool {
        return $job->organization->id === $organization->id;
    });
});

it('does not dispatch a digest when auto digest after sync is disabled', function (): void {
    Bus::fake();
    config(['judicial-sync.auto_digest_after_sync' => false]);

    $organization = Organization::factory()->create();

    app(DispatchOrganizationSyncDigest::class)->handle($organization->id);

    Bus::assertNotDispatched(SendOrganizationDigestJob::class);
});

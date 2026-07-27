<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Resources;

use Spatie\LaravelData\Resource;

/**
 * Summary of one organization's pending digest within the package preview.
 *
 * One consolidate email/package is sent per organization. Counts reflect only
 * actuaciones that would actually be included by {@see \Src\Application\Shared\Services\Notification\NotificationDigestService}
 * (ProcessAction + registration cutoff), not other notification types.
 */
class DigestPackageOrganizationResource extends Resource
{
    public function __construct(
        public string $organization_id,
        public string $organization_name,
        /** Distinct processes that will appear in this organization's consolidate. */
        public int $pending_processes,
        /** Eligible actuaciones (movements) included in that consolidate. */
        public int $pending_actions,
        /** @var array<string, list<string>> channel_type => list of active channel values */
        public array $channels,
    ) {}
}

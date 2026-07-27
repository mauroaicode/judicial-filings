<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Resources;

use Spatie\LaravelData\Resource;

/**
 * Read-only view of the pending digest package (no notifications are sent or marked).
 *
 * Semantics:
 * - One "consolidate" = one organization digest about to be sent.
 * - {@see $consolidates_ready} is the number of consolidates ready to enqueue.
 * - Process/action totals only include digest-eligible ProcessAction notifications.
 */
class DigestPackagePreviewResource extends Resource
{
    /**
     * @param  list<DigestPackageOrganizationResource>  $organizations
     */
    public function __construct(
        /** How many consolidates (one per organization) are ready to send. */
        public int $consolidates_ready,
        /** Sum of distinct processes across all consolidates in this package. */
        public int $total_pending_processes,
        /** Sum of eligible actuaciones across all consolidates in this package. */
        public int $total_pending_actions,
        public bool $auto_digest_enabled,
        public array $organizations,
    ) {}
}

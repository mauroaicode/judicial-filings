<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Resources;

use Spatie\LaravelData\Resource;

/**
 * Read-only view of the pending digest package (no notifications are sent or marked).
 */
class DigestPackagePreviewResource extends Resource
{
    /**
     * @param  list<DigestPackageOrganizationResource>  $organizations
     */
    public function __construct(
        public int $organizations_count,
        public int $total_pending_actions,
        public bool $auto_digest_enabled,
        public array $organizations,
    ) {}
}

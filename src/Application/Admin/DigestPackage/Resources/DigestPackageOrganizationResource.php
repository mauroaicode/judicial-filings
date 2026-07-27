<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Resources;

use Spatie\LaravelData\Resource;

/**
 * Summary of one organization's pending digest within the package preview.
 */
class DigestPackageOrganizationResource extends Resource
{
    public function __construct(
        public string $organization_id,
        public string $organization_name,
        public int $pending_actions,
        /** @var array<string, list<string>> channel_type => list of active channel values */
        public array $channels,
    ) {}
}

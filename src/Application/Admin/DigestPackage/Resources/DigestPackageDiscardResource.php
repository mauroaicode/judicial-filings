<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Resources;

use Spatie\LaravelData\Resource;

/**
 * Result of discarding (silencing without sending) one organization's pending consolidate.
 */
class DigestPackageDiscardResource extends Resource
{
    public function __construct(
        public string $organization_id,
        public string $organization_name,
        public int $actions_discarded,
        public string $message,
    ) {}
}

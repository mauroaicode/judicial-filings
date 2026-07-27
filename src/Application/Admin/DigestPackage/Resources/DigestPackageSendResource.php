<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Resources;

use Spatie\LaravelData\Resource;

/**
 * Result of dispatching the digest package.
 */
class DigestPackageSendResource extends Resource
{
    public function __construct(
        public int $organizations_queued,
        public string $message,
    ) {}
}

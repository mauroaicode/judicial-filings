<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notification\Resources;

use Spatie\LaravelData\Resource;

class OrganizationNotificationChannelLimitResource extends Resource
{
    public function __construct(
        public string $channel_type,
        public int $limit,
        public ?int $configured_limit,
        public int $default_limit,
        public int $used,
        public int $remaining,
        public bool $can_add,
    ) {}

    /**
     * @param  array{
     *     channel_type: string,
     *     limit: int,
     *     configured_limit: int|null,
     *     default_limit: int,
     *     used: int,
     *     remaining: int,
     *     can_add: bool
     * }  $row
     */
    public static function fromSummary(array $row): self
    {
        return new self(
            channel_type: $row['channel_type'],
            limit: $row['limit'],
            configured_limit: $row['configured_limit'],
            default_limit: $row['default_limit'],
            used: $row['used'],
            remaining: $row['remaining'],
            can_add: $row['can_add'],
        );
    }
}

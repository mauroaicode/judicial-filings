<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Resources;

use Spatie\LaravelData\Resource;

class OrganizationPresenceResource extends Resource
{
    public function __construct(
        public string $organization_id,
        public string $organization_name,
        public bool $is_active,
        public int $users_count,
        public ?string $last_seen_at,
        public ?int $days_inactive,
        public string $status,
    ) {}

    public static function fromRow(object $row, int $inactiveDays): self
    {
        $lastSeenAt = self::timestamp($row->last_seen_at ?? null);
        $daysInactive = self::daysInactive($lastSeenAt);

        return new self(
            organization_id: (string) $row->id,
            organization_name: (string) $row->name,
            is_active: (bool) $row->is_active,
            users_count: (int) ($row->users_count ?? 0),
            last_seen_at: $lastSeenAt,
            days_inactive: $daysInactive,
            status: self::status($lastSeenAt, $daysInactive, $inactiveDays),
        );
    }

    private static function status(?string $lastSeenAt, ?int $daysInactive, int $inactiveDays): string
    {
        if ($lastSeenAt === null) {
            return 'never';
        }

        if ($daysInactive !== null && $daysInactive >= $inactiveDays) {
            return 'stale';
        }

        return 'recent';
    }

    private static function daysInactive(?string $lastSeenAt): ?int
    {
        if ($lastSeenAt === null) {
            return null;
        }

        return (int) \Illuminate\Support\Facades\Date::parse($lastSeenAt)
            ->startOfDay()
            ->diffInDays(today());
    }

    private static function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}

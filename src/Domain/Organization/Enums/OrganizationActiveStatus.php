<?php

declare(strict_types=1);

namespace Src\Domain\Organization\Enums;

enum OrganizationActiveStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    /**
     * Get the label for the status.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('enums.organization_active_status.active'),
            self::INACTIVE => __('enums.organization_active_status.inactive'),
        };
    }

    /**
     * Get all cases as array for API listing.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->getLabel(),
            ],
            self::cases()
        );
    }
}

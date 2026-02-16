<?php

declare(strict_types=1);

namespace Src\Domain\Organization\Enums;

enum OrganizationType: string
{
    case NATURAL = 'natural';
    case JURIDICAL = 'juridical';

    /**
     * Get the label for the type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::NATURAL => __('enums.organization_type.natural'),
            self::JURIDICAL => __('enums.organization_type.juridical'),
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
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->getLabel(),
            ],
            self::cases()
        );
    }
}

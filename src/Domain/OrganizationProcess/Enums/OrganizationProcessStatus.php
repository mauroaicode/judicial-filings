<?php

declare(strict_types=1);

namespace Src\Domain\OrganizationProcess\Enums;

enum OrganizationProcessStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    /**
     * Get the label for the status.
     */
    public function getLabel(): string
    {
        return __('enums.organization_process_status.'.$this->value);
    }

    /**
     * Get all status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get status from boolean is_active value.
     */
    public static function fromBoolean(bool $isActive): self
    {
        return $isActive ? self::ACTIVE : self::INACTIVE;
    }
}

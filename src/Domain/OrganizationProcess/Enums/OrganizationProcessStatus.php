<?php

declare(strict_types=1);

namespace Src\Domain\OrganizationProcess\Enums;

enum OrganizationProcessStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

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

    /**
     * Resolve status from the organization_processes pivot.
     */
    public static function fromPivot(mixed $pivot): self
    {
        if ($pivot === null) {
            return self::INACTIVE;
        }

        $statusValue = $pivot->status ?? null;

        if ($statusValue instanceof self) {
            return $statusValue;
        }

        if (is_string($statusValue) && $statusValue !== '') {
            return self::tryFrom($statusValue) ?? self::fromBoolean((bool) ($pivot->is_active ?? false));
        }

        return self::fromBoolean((bool) ($pivot->is_active ?? false));
    }

    /**
     * Whether the organization still tracks the process (active or suspended).
     */
    public function toIsActive(): bool
    {
        return $this !== self::INACTIVE;
    }
}

<?php

declare(strict_types=1);

namespace Src\Domain\OrganizationProcess\Enums;

enum OrganizationProcessStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING_HEARING = 'pending_hearing';
    case SUSPENDED_INSOLVENCY = 'suspended_insolvency';
    case SUSPENDED_PAYMENT_AGREEMENT = 'suspended_payment_agreement';
    /** @deprecated Prefer a specific paused status. Kept for existing rows. */
    case SUSPENDED = 'suspended';

    /**
     * Get the label for the status.
     */
    public function getLabel(): string
    {
        return __('enums.organization_process_status.'.$this->value);
    }

    /**
     * Inactivity semaphore is paused and Activo/Inactivo toggle is blocked.
     */
    public function isPaused(): bool
    {
        return match ($this) {
            self::PENDING_HEARING,
            self::SUSPENDED_INSOLVENCY,
            self::SUSPENDED_PAYMENT_AGREEMENT,
            self::SUSPENDED => true,
            self::ACTIVE,
            self::INACTIVE => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function pausedCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $status): bool => $status->isPaused(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function pausedValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::pausedCases(),
        );
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
     * Whether the organization still tracks the process (active or paused/suspended variants).
     */
    public function toIsActive(): bool
    {
        return $this !== self::INACTIVE;
    }
}

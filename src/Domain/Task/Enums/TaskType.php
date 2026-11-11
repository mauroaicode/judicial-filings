<?php

declare(strict_types=1);

namespace Src\Domain\Task\Enums;

use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;

enum TaskType: string
{
    case GENERAL = 'general';
    case PENDING_HEARING = 'pending_hearing';
    case SUSPENSION_INSOLVENCY = 'suspension_insolvency';
    case SUSPENSION_PAYMENT_AGREEMENT = 'suspension_payment_agreement';
    /** @deprecated Prefer a specific pausing type. Kept for existing rows. */
    case SUSPENSION = 'suspension';

    /**
     * Get the label for the type.
     */
    public function getLabel(): string
    {
        return __('enums.task_type.'.$this->value);
    }

    /**
     * Whether creating/updating this task pauses process inactivity tracking.
     */
    public function pausesProcess(): bool
    {
        return match ($this) {
            self::PENDING_HEARING,
            self::SUSPENSION_INSOLVENCY,
            self::SUSPENSION_PAYMENT_AGREEMENT,
            self::SUSPENSION => true,
            self::GENERAL => false,
        };
    }

    /**
     * Organization-process status applied when this task type is active.
     */
    public function toOrganizationProcessStatus(): OrganizationProcessStatus
    {
        return match ($this) {
            self::PENDING_HEARING => OrganizationProcessStatus::PENDING_HEARING,
            self::SUSPENSION_INSOLVENCY => OrganizationProcessStatus::SUSPENDED_INSOLVENCY,
            self::SUSPENSION_PAYMENT_AGREEMENT => OrganizationProcessStatus::SUSPENDED_PAYMENT_AGREEMENT,
            self::SUSPENSION => OrganizationProcessStatus::SUSPENDED,
            self::GENERAL => OrganizationProcessStatus::ACTIVE,
        };
    }

    /**
     * @return list<self>
     */
    public static function pausingCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => $type->pausesProcess(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function pausingValues(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::pausingCases(),
        );
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
            // Hide deprecated legacy type from create/edit pickers.
            array_values(array_filter(
                self::cases(),
                static fn (self $type): bool => $type !== self::SUSPENSION,
            )),
        );
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

declare(strict_types=1);

namespace Src\Domain\AppUser\Enums;

enum AppUserRole: string
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';

    /**
     * Get label for a role by string value
     */
    public static function getLabelFor(string $roleName): ?string
    {
        $role = self::tryFrom($roleName);

        return $role?->getLabel();
    }

    /**
     * Get all role values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get role values that can create users
     *
     * @return array<string>
     */
    public static function canCreateUsers(): array
    {
        return [
            self::ADMIN->value,
        ];
    }

    /**
     * Get the label for the role
     *
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => __('enums.app_user_role.admin'),
            self::CUSTOMER => __('enums.app_user_role.customer'),
        };
    }
}

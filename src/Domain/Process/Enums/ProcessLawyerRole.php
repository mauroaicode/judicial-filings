<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

enum ProcessLawyerRole: string
{
    case PLAINTIFF = 'plaintiff';
    case DEFENDANT = 'defendant';

    /**
     * Get the label for the role.
     */
    public function getLabel(): string
    {
        return __('enums.process_lawyer_role.'.$this->value);
    }

    /**
     * Get all role values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

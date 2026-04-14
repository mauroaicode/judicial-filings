<?php

declare(strict_types=1);

namespace Src\Domain\Shared\Enums;

enum SeverityColor: string
{
    case RED = 'red';
    case YELLOW = 'yellow';
    case GREEN = 'green';

    /**
     * Get the label for the color.
     */
    public function getLabel(): string
    {
        return __('enums.severity_color.'.$this->value);
    }

    /**
     * Get all color values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Enums;

enum NotificationDigestOpenedVia: string
{
    case App = 'app';

    case Email = 'email';

    public static function fromRequest(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? $value : '') ?? self::App;
    }
}

<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Enums;

enum NotificationChannelType: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Internal = 'internal';

    /**
     * Channels an organization can configure (internal stays system-managed).
     *
     * @return list<string>
     */
    public static function configurable(): array
    {
        return [
            self::Email->value,
            self::WhatsApp->value,
            self::Sms->value,
        ];
    }
}

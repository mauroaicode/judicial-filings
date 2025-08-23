<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Enums;

enum NotificationChannelType: string
{
    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';
    case SMS = 'sms';
    case INTERNAL = 'internal';

    /**
     * Get active channels for notifications
     * You can modify this method to enable/disable specific channels
     */
    public static function getActiveChannels(): array
    {
        return [
            self::EMAIL,
            // self::WHATSAPP,
            // self::SMS,
            // self::INTERNAL,
        ];
    }
}

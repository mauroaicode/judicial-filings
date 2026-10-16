<?php

declare(strict_types=1);

namespace Src\Domain\Organization\Enums;

/**
 * Product-level organization status controlled by the admin active-status endpoint.
 */
enum OrganizationStatusMode: string
{
    /**
     * Login allowed, process sync and notifications enabled.
     */
    case Active = 'active';

    /**
     * Login blocked, process sync and notifications disabled.
     */
    case Inactive = 'inactive';

    /**
     * Login allowed, but process sync and notifications paused.
     */
    case TrackingPaused = 'tracking_paused';

    public function allowsLogin(): bool
    {
        return $this !== self::Inactive;
    }

    public function allowsProcessTracking(): bool
    {
        return $this === self::Active;
    }
}

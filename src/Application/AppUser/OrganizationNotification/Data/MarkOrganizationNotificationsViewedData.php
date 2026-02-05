<?php

declare(strict_types=1);

namespace Src\Application\AppUser\OrganizationNotification\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class MarkOrganizationNotificationsViewedData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[MapInputName('notification_ids')]
        #[ArrayType]
        public array $notification_ids = [],
    ) {}

    /**
     * @return array<string>
     */
    public function getNotificationIds(): array
    {
        return array_values(array_filter(array_map(strval(...), $this->notification_ids)));
    }
}

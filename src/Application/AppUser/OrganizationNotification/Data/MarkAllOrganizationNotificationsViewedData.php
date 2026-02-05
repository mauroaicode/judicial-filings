<?php

declare(strict_types=1);

namespace Src\Application\AppUser\OrganizationNotification\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class MarkAllOrganizationNotificationsViewedData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Nullable]
        #[In(['actuacion', 'actuacion_alerta'])]
        public ?string $type = null,
    ) {}
}

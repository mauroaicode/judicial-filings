<?php

declare(strict_types=1);

namespace Src\Application\AppUser\OrganizationNotification\Data;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class OrganizationNotificationIndexData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required]
        #[In(['actuacion', 'actuacion_alerta'])]
        public string $type,
        public bool $viewed = false,
        #[IntegerType, Min(1), Max(100)]
        public int $per_page = 20,
        #[IntegerType, Min(1)]
        public int $page = 1,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'type.in' => __('validation.in', ['attribute' => __('data.type')]),
        ]);
    }
}

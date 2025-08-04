<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Application\Data;

use Core\Shared\Infrastructure\Traits\TranslatableDataAttributesTrait;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class CreateAppUserData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, Min(2), Max(255)]
        public string $name,

        #[Required, Min(2), Max(255)]
        public string $last_name,

        #[Required, Min(2), Max(255)]
        public string $slug,

        #[Required, Email, Unique('app_users', 'email')]
        public string $email,

        #[Required, Password(
            min: 12,
            letters: true,
            mixedCase: true,
            numbers: true,
            symbols: true
        )]
        public string $password,
    ) {}
}

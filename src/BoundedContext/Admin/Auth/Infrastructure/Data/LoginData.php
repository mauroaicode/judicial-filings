<?php

namespace Core\BoundedContext\Admin\Auth\Infrastructure\Data;

use Spatie\LaravelData\Attributes\Validation\{
    Email,
    Required
};
use Spatie\LaravelData\Data;
use Core\Shared\Infrastructure\Traits\TranslatableDataAttributesTrait;


class LoginData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, Email]
        public readonly string $email,

        #[Required]
        public readonly string $password,
    ) {}
}

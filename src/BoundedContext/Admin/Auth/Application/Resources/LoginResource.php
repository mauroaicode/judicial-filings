<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\Auth\Application\Resources;

use Spatie\LaravelData\Resource;


class LoginResource extends Resource
{
    public function __construct(
        public string $token,
    ) {}

    /**
     * Create LoginResource from user and token
     */
    public static function fromToken(string $token): self
    {
        return new self(
            token: $token
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Shared\Presence;

final readonly class IpLocation
{
    public function __construct(
        public ?string $country,
        public ?string $region,
        public ?string $city,
    ) {}

    public function isEmpty(): bool
    {
        return $this->blank($this->country)
            && $this->blank($this->region)
            && $this->blank($this->city);
    }

    private function blank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}

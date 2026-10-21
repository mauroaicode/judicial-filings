<?php

declare(strict_types=1);

namespace Src\Application\Shared\Presence;

interface IpLocationLookup
{
    public function locate(string $ip): ?IpLocation;
}

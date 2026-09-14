<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

enum ManualRegistrationRequestStatus: string
{
    case Pending = 'pending';
    case Registered = 'registered';
    case Rejected = 'rejected';
}

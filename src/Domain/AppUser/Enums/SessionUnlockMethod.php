<?php

declare(strict_types=1);

namespace Src\Domain\AppUser\Enums;

enum SessionUnlockMethod: string
{
    case PASSWORD = 'password';
    case OTP = 'otp';
}

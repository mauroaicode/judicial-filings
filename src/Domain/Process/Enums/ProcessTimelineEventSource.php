<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

enum ProcessTimelineEventSource: string
{
    case JUDICIAL_BRANCH = 'judicial_branch';
    case SAMAI = 'samai';
    case USER = 'user';
    case SYSTEM = 'system';
    case BACKFILL = 'backfill';
}

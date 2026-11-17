<?php

declare(strict_types=1);

namespace Src\Domain\CourtDirectory\Enums;

enum CourtDirectorySyncTrigger: string
{
    case Command = 'command';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return __('enums.court_directory_sync_trigger.'.$this->value);
    }
}

<?php

declare(strict_types=1);

namespace Src\Domain\CourtDirectory\Enums;

enum CourtDirectorySyncStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __('enums.court_directory_sync_status.'.$this->value);
    }
}

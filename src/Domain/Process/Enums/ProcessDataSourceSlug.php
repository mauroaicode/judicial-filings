<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

/**
 * Canonical slugs for rows in {@see \Src\Domain\Process\Models\ProcessDataSource}.
 */
enum ProcessDataSourceSlug: string
{
    case JudicialBranch = 'judicial_branch';

    case Samai = 'samai';
}

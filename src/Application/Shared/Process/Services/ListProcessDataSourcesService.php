<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Illuminate\Support\Collection;
use Src\Domain\Process\Models\ProcessDataSource;

class ListProcessDataSourcesService
{
    /**
     * Active process data sources (Rama Judicial, SAMAI, Publicaciones Procesales, …), ordered by label.
     *
     * @return Collection<int, ProcessDataSource>
     */
    public function handle(): Collection
    {
        return ProcessDataSource::query()
            ->whereActive()
            ->orderedByName()
            ->get();
    }
}

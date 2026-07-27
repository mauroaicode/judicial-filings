<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\ProcessDataSource;

/**
 * @extends Builder<ProcessDataSource>
 */
class ProcessDataSourceQueryBuilder extends Builder
{
    /**
     * @return $this
     */
    public function whereActive(): self
    {
        $this->where('is_active', true);

        return $this;
    }

    /**
     * @return $this
     */
    public function orderedByName(): self
    {
        $this->orderBy('name');

        return $this;
    }

    /**
     * Sources usable for admin private Excel import.
     *
     * @return $this
     */
    public function forPrivateExcelImport(): self
    {
        $this->whereIn('slug', ProcessDataSourceSlug::privateExcelImportValues());

        return $this;
    }

    /**
     * Sources backed by external consultation APIs.
     *
     * @return $this
     */
    public function forApiConsultation(): self
    {
        $this->whereIn('slug', ProcessDataSourceSlug::apiConsultationValues());

        return $this;
    }

    /**
     * @return $this
     */
    public function whereSlug(string $slug): self
    {
        $this->where('slug', $slug);

        return $this;
    }
}

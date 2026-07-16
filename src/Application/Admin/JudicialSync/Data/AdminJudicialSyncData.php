<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;

class AdminJudicialSyncData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Nullable, Regex('/^\d{23}$/')]
        public ?string $radicado = null,
        /**
         * Which source to sync. Defaults to Rama Judicial for backward compatibility.
         * Accepted executable values: judicial_branch | samai.
         */
        #[In([
            'judicial_branch',
            'samai',
        ])]
        public string $data_source = 'judicial_branch',
    ) {}

    public function dataSource(): JudicialSyncDataSource
    {
        return JudicialSyncDataSource::from($this->data_source);
    }
}

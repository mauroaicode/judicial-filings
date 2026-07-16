<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;

class ProcessImportFromExcelData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, Exists('organizations', 'id')]
        public readonly string $organization_id,
        #[Required, File, Mimes('xlsx', 'xls')]
        public readonly UploadedFile $file,
        /** Fuente de los procesos. Por defecto rama judicial. Enviar "samai" para importar desde SAMAI. */
        #[Enum(ProcessDataSourceSlug::class)]
        public readonly ProcessDataSourceSlug $source = ProcessDataSourceSlug::JudicialBranch,
    ) {}

    /**
     * El admin UI envía `data_source_slug` (mismo nombre que private-import).
     * También aceptamos `source` para clientes/API que ya usan ese campo.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $sourceMissing = ! array_key_exists('source', $properties)
            || $properties['source'] === null
            || $properties['source'] === '';

        if ($sourceMissing && isset($properties['data_source_slug'])) {
            $properties['source'] = $properties['data_source_slug'];
        }

        return $properties;
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'organization_id.required' => __('validation.organization_id.required'),
            'organization_id.exists' => __('validation.organization_id.exists'),
            'file.required' => __('validation.file.required'),
            'file.file' => __('validation.file.file'),
            'file.mimes' => __('validation.file.mimes', ['values' => 'xlsx, xls']),
        ]);
    }
}

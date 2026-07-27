<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;

class PrivateProcessExcelImportFromExcelData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, Exists('organizations', 'id')]
        public readonly string $organization_id,
        #[Required, File, Mimes('xlsx', 'xls')]
        public readonly UploadedFile $file,
        public readonly ?string $data_source_slug = null,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->addRules([
            'data_source_slug' => [
                'nullable',
                'string',
                Rule::in(ProcessDataSourceSlug::privateExcelImportValues()),
                Rule::exists('process_data_sources', 'slug')->where(
                    fn ($q) => $q->where('is_active', true)
                ),
            ],
        ]);

        $validator->setCustomMessages([
            'organization_id.required' => __('validation.organization_id.required'),
            'organization_id.exists' => __('validation.organization_id.exists'),
            'file.required' => __('validation.file.required'),
            'file.file' => __('validation.file.file'),
            'file.mimes' => __('validation.file.mimes', ['values' => 'xlsx, xls']),
            'data_source_slug.in' => __('process.private_process_import_invalid_data_source'),
        ]);
    }
}

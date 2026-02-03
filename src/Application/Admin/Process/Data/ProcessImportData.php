<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class ProcessImportData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, File, Mimes('xlsx', 'xls')]
        public readonly UploadedFile $file,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'file.required' => __('validation.file.required'),
            'file.file' => __('validation.file.file'),
            'file.mimes' => __('validation.file.mimes', ['values' => 'xlsx, xls']),
        ]);
    }
}

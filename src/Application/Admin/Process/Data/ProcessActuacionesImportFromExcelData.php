<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

/**
 * Request data for importing actuaciones/movements from Excel.
 *
 * No organization or data source is required: the system resolves processes
 * automatically by radicado (process_number). Radicados not found in the
 * database are returned in the response so the admin can create them first
 * via the regular "Importar Procesos" flow.
 */
class ProcessActuacionesImportFromExcelData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, File, Mimes('xlsx', 'xls')]
        public readonly UploadedFile $file,
    ) {}

    public static function messages(): array
    {
        return [
            'file.required' => __('validation.file.required'),
            'file.file' => __('validation.file.file'),
            'file.mimes' => __('validation.file.mimes', ['values' => 'xlsx, xls']),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class UploadProcessActionDocumentsData extends Data
{
    use TranslatableDataAttributesTrait;

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $organization_ids
     */
    public function __construct(
        #[Required, ArrayType]
        public readonly array $files,

        #[Required, ArrayType]
        public readonly array $organization_ids,
    ) {}

    public static function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'required',
                File::types(['pdf'])->max(10 * 1024),
            ],
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => ['required', 'uuid', 'exists:organizations,id'],
        ];
    }

    public static function attributes(): array
    {
        return [
            'files' => __('data.files'),
            'files.*' => __('data.files.*'),
            'organization_ids' => __('data.organization_ids'),
            'organization_ids.*' => __('data.organization_ids.*'),
        ];
    }
}

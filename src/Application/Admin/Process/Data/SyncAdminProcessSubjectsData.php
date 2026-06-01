<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class SyncAdminProcessSubjectsData extends Data
{
    use TranslatableDataAttributesTrait;

    /**
     * @param  list<AdminProcessSubjectItemData>  $subjects
     */
    public function __construct(
        #[Required, Min(1)]
        public array $subjects,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.id' => ['nullable', 'uuid'],
            'subjects.*.subject_type' => ['required', 'string', 'max:255'],
            'subjects.*.name_or_business_name' => ['required', 'string'],
        ];
    }
}

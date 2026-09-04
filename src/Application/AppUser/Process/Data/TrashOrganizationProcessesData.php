<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Data;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class TrashOrganizationProcessesData extends Data
{
    use TranslatableDataAttributesTrait;

    /**
     * @param  list<string>|null  $process_ids
     */
    public function __construct(
        #[BooleanType]
        public readonly bool $all = false,
        #[ArrayType]
        public readonly ?array $process_ids = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'all' => ['sometimes', 'boolean'],
            'process_ids' => ['sometimes', 'nullable', 'array', 'min:1'],
            'process_ids.*' => ['required', 'uuid', 'distinct', 'exists:processes,id'],
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();
            $all = filter_var($data['all'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $processIds = $data['process_ids'] ?? null;

            if ($all && is_array($processIds) && $processIds !== []) {
                $validator->errors()->add(
                    'process_ids',
                    __('validation.prohibited', ['attribute' => 'process_ids']),
                );

                return;
            }

            if (! $all && (! is_array($processIds) || $processIds === [])) {
                $validator->errors()->add(
                    'process_ids',
                    __('validation.required', ['attribute' => 'process_ids']),
                );
            }
        });
    }
}

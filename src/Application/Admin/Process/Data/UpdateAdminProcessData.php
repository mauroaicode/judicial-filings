<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class UpdateAdminProcessData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public readonly string|Optional $court = new Optional,
        public readonly string|null|Optional $speaker = new Optional,
        public readonly string|Optional $department = new Optional,
        public readonly string|Optional $process_type = new Optional,
        public readonly string|Optional $process_class = new Optional,
        public readonly string|null|Optional $subclass_process = new Optional,
        public readonly string|null|Optional $location = new Optional,
        public readonly string|Optional $process_date = new Optional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $dateRules = ['sometimes', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'];

        return [
            'court' => ['sometimes', 'string', 'min:1', 'max:255'],
            'speaker' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'string', 'min:1', 'max:255'],
            'process_type' => ['sometimes', 'string', 'min:1', 'max:255'],
            'process_class' => ['sometimes', 'string', 'min:1', 'max:255'],
            'subclass_process' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'process_date' => $dateRules,
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provided = array_intersect(
                array_keys($validator->getData()),
                self::editableFields(),
            );

            if ($provided === []) {
                $validator->errors()->add('payload', __('process.admin_update_requires_at_least_one_field'));
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function editableFields(): array
    {
        return [
            'court',
            'speaker',
            'department',
            'process_type',
            'process_class',
            'subclass_process',
            'location',
            'process_date',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toProcessUpdates(): array
    {
        $updates = [];

        foreach (self::editableFields() as $field) {
            $value = $this->{$field};

            if ($value instanceof Optional) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if (in_array($field, ['speaker', 'subclass_process', 'location'], true)) {
                $updates[$field] = $value === '' ? null : $value;

                continue;
            }

            $updates[$field] = $value;
        }

        return $updates;
    }
}

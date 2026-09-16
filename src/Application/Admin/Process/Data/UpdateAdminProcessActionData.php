<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class UpdateAdminProcessActionData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        public readonly string|Optional $action_date = new Optional,
        public readonly string|null|Optional $term_start_date = new Optional,
        public readonly string|null|Optional $term_end_date = new Optional,
        public readonly string|Optional $registration_date = new Optional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $requiredDate = ['sometimes', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'];
        $nullableDate = ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'];

        return [
            'action_date' => $requiredDate,
            'term_start_date' => $nullableDate,
            'term_end_date' => $nullableDate,
            'registration_date' => $requiredDate,
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();
            $provided = array_intersect(array_keys($data), self::editableFields());

            if ($provided === []) {
                $validator->errors()->add('payload', __('process.admin_update_requires_at_least_one_field'));
            }

            $start = $data['term_start_date'] ?? null;
            $end = $data['term_end_date'] ?? null;

            if (is_string($start) && $start !== '' && is_string($end) && $end !== '' && $end < $start) {
                $validator->errors()->add('term_end_date', __('process.term_end_before_start'));
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function editableFields(): array
    {
        return [
            'action_date',
            'term_start_date',
            'term_end_date',
            'registration_date',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toActionUpdates(): array
    {
        $map = [
            'action_date' => 'action_date',
            'term_start_date' => 'start_date',
            'term_end_date' => 'end_date',
            'registration_date' => 'registration_date',
        ];

        $updates = [];

        foreach ($map as $payloadField => $column) {
            $value = $this->{$payloadField};

            if ($value instanceof Optional) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if (in_array($column, ['start_date', 'end_date'], true)) {
                $updates[$column] = $value === '' ? null : $value;

                continue;
            }

            $updates[$column] = $value;
        }

        return $updates;
    }
}

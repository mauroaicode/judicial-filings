<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ProcessLawyerRole;

class StoreManualRegistrationRequestData extends Data
{
    use TranslatableDataAttributesTrait;

    /**
     * @param  list<array{name: string, identification?: string|null}>  $plaintiffs
     * @param  list<array{name: string, identification?: string|null}>  $defendants
     * @param  list<array{name: string, identification?: string|null}>  $other_subjects
     */
    public function __construct(
        #[Required, Regex('/^\d{23}$/')]
        public readonly string $process_number,
        #[Required, Enum(ManualRegistrationRequestReason::class)]
        public readonly ManualRegistrationRequestReason $reason,
        #[Required, Enum(ProcessLawyerRole::class)]
        public readonly ProcessLawyerRole $lawyer_role,
        #[Required, StringType, Max(255)]
        public readonly string $process_class,
        public readonly array $plaintiffs,
        public readonly array $defendants,
        public readonly array $other_subjects = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'plaintiffs' => ['required', 'array', 'min:1'],
            'plaintiffs.*.name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'plaintiffs.*.identification' => ['nullable', 'string', 'max:50'],
            'defendants' => ['required', 'array', 'min:1'],
            'defendants.*.name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'defendants.*.identification' => ['nullable', 'string', 'max:50'],
            'other_subjects' => ['nullable', 'array'],
            'other_subjects.*.name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'other_subjects.*.identification' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'plaintiffs.required' => __('process.manual_registration_plaintiffs_required'),
            'plaintiffs.min' => __('process.manual_registration_plaintiffs_required'),
            'plaintiffs.*.name.required' => __('process.manual_registration_subject_name_required'),
            'plaintiffs.*.name.regex' => __('process.manual_registration_subject_name_required'),
            'defendants.required' => __('process.manual_registration_defendants_required'),
            'defendants.min' => __('process.manual_registration_defendants_required'),
            'defendants.*.name.required' => __('process.manual_registration_subject_name_required'),
            'defendants.*.name.regex' => __('process.manual_registration_subject_name_required'),
            'other_subjects.*.name.required' => __('process.manual_registration_subject_name_required'),
            'other_subjects.*.name.regex' => __('process.manual_registration_subject_name_required'),
        ];
    }

    /**
     * @return list<array{name: string, identification: string|null}>
     */
    public function normalizedPlaintiffs(): array
    {
        return $this->normalizeSubjects($this->plaintiffs);
    }

    /**
     * @return list<array{name: string, identification: string|null}>
     */
    public function normalizedDefendants(): array
    {
        return $this->normalizeSubjects($this->defendants);
    }

    /**
     * @return list<array{name: string, identification: string|null}>
     */
    public function normalizedOtherSubjects(): array
    {
        return $this->normalizeSubjects($this->other_subjects);
    }

    /**
     * @param  list<array{name?: string, identification?: string|null}>  $subjects
     * @return list<array{name: string, identification: string|null}>
     */
    private function normalizeSubjects(array $subjects): array
    {
        $normalized = [];

        foreach ($subjects as $subject) {
            $name = trim((string) ($subject['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $identification = $subject['identification'] ?? null;
            $identification = is_string($identification) ? trim($identification) : null;

            $normalized[] = [
                'name' => $name,
                'identification' => $identification === '' ? null : $identification,
            ];
        }

        return $normalized;
    }
}

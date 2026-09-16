<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Data;

use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\Process\Enums\ProcessLawyerRole;

class RegisterManualRegistrationRequestData extends Data
{
    use TranslatableDataAttributesTrait;

    /**
     * @param  list<array{name: string, identification?: string|null}>|null  $plaintiffs
     * @param  list<array{name: string, identification?: string|null}>|null  $defendants
     * @param  list<array{name: string, identification?: string|null}>|null  $other_subjects
     */
    public function __construct(
        #[Nullable, StringType, Max(255)]
        public readonly ?string $process_class = null,
        #[Nullable, Enum(ProcessLawyerRole::class)]
        public readonly ?ProcessLawyerRole $lawyer_role = null,
        #[Nullable, StringType, Max(255)]
        public readonly ?string $court = null,
        #[Nullable, StringType, Max(255)]
        public readonly ?string $speaker = null,
        #[Nullable, StringType, Max(255)]
        public readonly ?string $subclass_process = null,
        #[Nullable, StringType, Max(255)]
        public readonly ?string $location = null,
        public readonly ?array $plaintiffs = null,
        public readonly ?array $defendants = null,
        public readonly ?array $other_subjects = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'plaintiffs' => ['nullable', 'array', 'min:1'],
            'plaintiffs.*.name' => ['required', 'string', 'max:255'],
            'plaintiffs.*.identification' => ['nullable', 'string', 'max:50'],
            'defendants' => ['nullable', 'array', 'min:1'],
            'defendants.*.name' => ['required', 'string', 'max:255'],
            'defendants.*.identification' => ['nullable', 'string', 'max:50'],
            'other_subjects' => ['nullable', 'array'],
            'other_subjects.*.name' => ['required', 'string', 'max:255'],
            'other_subjects.*.identification' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @param  list<array{name?: string, identification?: string|null}>|null  $subjects
     * @return list<array{name: string, identification: string|null}>|null
     */
    public function normalized(?array $subjects): ?array
    {
        if ($subjects === null) {
            return null;
        }

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

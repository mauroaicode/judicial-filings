<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Profile\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;

class UpdateProfileData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required]
        public string $name,

        #[Required]
        public string $last_name,

        #[Required]
        public string $email,

        #[Required]
        public string $identification,

        #[Nullable, Min(8), Confirmed]
        public ?string $password = null,
    ) {}

    public static function rules(): array
    {
        $appUserId = auth()->id();

        return [
            'email' => [
                'required',
                'email',
                Rule::unique('app_users', 'email')->ignore($appUserId, 'id'),
            ],
            'identification' => [
                'required',
                Rule::unique('app_users', 'identification')->ignore($appUserId, 'id'),
            ],
        ];
    }
}

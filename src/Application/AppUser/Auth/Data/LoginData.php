<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Data;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\AppUser\Models\AppUser;

class LoginData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required]
        public readonly string $identification,

        #[Required]
        public readonly string $password,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();

            // Only validate if identification and password are present
            if (! isset($data['identification']) || ! isset($data['password'])) {
                return;
            }

            $appUser = AppUser::query()->where('identification', $data['identification'])->first();

            if (! $appUser) {
                $validator->errors()->add('identification', __('auth.failed'));

                return;
            }

            if (is_null($appUser->email_verified_at)) {
                $validator->errors()->add('identification', __('auth.email_not_verified'));

                return;
            }

            if (! $appUser->belongsToActiveOrganization()) {
                $validator->errors()->add('identification', __('auth.user_inactive'));

                return;
            }

            if (! Hash::check(value: $data['password'], hashedValue: $appUser->password)) {
                $validator->errors()->add('identification', __('auth.failed'));
            }
        });
    }
}

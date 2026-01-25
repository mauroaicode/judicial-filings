<?php

declare(strict_types=1);

namespace Src\Application\Admin\Auth\Data;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

class LoginData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, Email]
        public readonly string $email,

        #[Required]
        public readonly string $password,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();

            // Only validate if email and password are present
            if (! isset($data['email']) || ! isset($data['password'])) {
                return;
            }

            $user = User::query()->whereEmail($data['email'])->first();

            if (! $user) {
                $validator->errors()->add('email', __('auth.failed'));

                return;
            }

            if (is_null($user->email_verified_at)) {
                $validator->errors()->add('email', __('auth.email_not_verified'));

                return;
            }

            if ($user->state !== UserStatus::ACTIVE) {
                $validator->errors()->add('email', __('auth.user_inactive'));

                return;
            }

            if (! Hash::check(value: $data['password'], hashedValue: $user->password)) {
                $validator->errors()->add('email', __('auth.failed'));
            }
        });
    }
}

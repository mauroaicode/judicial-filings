<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Data;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\AppUser\Enums\SessionUnlockMethod;
use Src\Domain\AppUser\Models\AppUser;

class UpdateSessionLockConfigData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, BooleanType]
        public bool $session_lock_enabled,

        #[Required, IntegerType, Min(1)]
        public int $session_lock_timeout,

        #[Nullable, Enum(SessionUnlockMethod::class)]
        public ?SessionUnlockMethod $session_unlock_method = null,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (($validator->getData()['session_unlock_method'] ?? null) !== SessionUnlockMethod::OTP->value) {
                return;
            }

            /** @var AppUser $appUser */
            $appUser = Auth::user();

            if (! $appUser->google_2fa_enabled) {
                $validator->errors()->add('session_unlock_method', __('auth.session_unlock_otp_requires_2fa'));
            }
        });
    }
}

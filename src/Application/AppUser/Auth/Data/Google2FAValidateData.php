<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Data;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use PragmaRX\Google2FALaravel\Google2FA;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\AppUser\Models\AppUser;

class Google2FAValidateData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[Required, Min(6)]
        public readonly string $one_time_password,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->getData();

            /** @var AppUser $appUser */
            $appUser = Auth::user();

            $secret = $appUser->google_2fa_secret;

            if (! $appUser->google_2fa_enabled || ! is_string($secret) || $secret === '') {
                abort(400, __('auth.no_2fa_setup'));
            }

            $valid = resolve(Google2FA::class)->verifyKey($secret, $data['one_time_password']);

            if (! $valid) {
                abort(422, __('auth.invalid_otp'));
            }
        });
    }
}

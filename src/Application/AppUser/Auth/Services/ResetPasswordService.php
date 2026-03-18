<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Src\Application\AppUser\Auth\Data\ResetPasswordData;
use Src\Domain\AppUser\Models\AppUser;

class ResetPasswordService
{
    public function handle(ResetPasswordData $data): void
    {
        $appUser = $this->findUser($data->identification);

        if (is_null($appUser)) {
            $this->throwValidationError('identification', __('auth.user_not_found'));
        }

        if (! $this->isValidToken($appUser->email, $data->token)) {
            $this->throwValidationError('token', __('auth.invalid_token'));
        }

        $this->updatePassword($appUser, $data->password);

        $this->deleteToken($appUser->email);
    }

    private function findUser(string $identification): ?AppUser
    {
        return AppUser::query()->where('identification', $identification)->first();
    }

    private function isValidToken(string $email, string $token): bool
    {
        $record = (array) DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (empty($record)) {
            return false;
        }

        $createdAt = $record['created_at'];
        $expire = config('auth.passwords.users.expire');

        if (now()->parse($createdAt)->addMinutes($expire)->isPast()) {
            return false;
        }

        return Hash::check($token, $record['token']);
    }

    private function updatePassword(AppUser $user, string $password): void
    {
        $user->update([
            'password' => Hash::make($password),
            'must_change_password' => false,
        ]);
    }

    private function deleteToken(string $email): void
    {
        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }

    private function throwValidationError(string $field, string $message): void
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
